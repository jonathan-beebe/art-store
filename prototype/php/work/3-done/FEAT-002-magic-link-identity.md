---
id: FEAT-002
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-002: Magic-link identity for sellers and customers with anonymous merge

## Problem
Both sites need passwordless sign-in and account creation, and the storefront needs an anonymous customer identity that survives into a verified account. Nothing exists yet beyond the scaffold from FEAT-001.

## Goal
Any visitor can become a verified seller or customer by clicking a magic link, and a customer's anonymous history follows them into their account.

## Outcome
- A visitor to `/seller/login` enters an email; a magic link appears in the debug alert; clicking it creates the seller (first visit) or signs them in (returning), lands on `/seller`, and the link is single-use and expires after 15 minutes.
- A visitor to `/login` (storefront) gets the same flow under the `customer` guard, landing on `/account`.
- Every storefront visitor has an anonymous `customers` row whose id is carried in an encrypted cookie; the row is created on first request and resolved on every request by middleware. `customer()` is available to storefront controllers and views regardless of sign-in.
- When an anonymous customer verifies an email that already belongs to a verified customer, the anonymous row's favorites, cart, orders, listing events, and notifications are re-pointed to the verified customer, a `customer_merges` row is written, and a stale cookie holding the merged id resolves to the verified customer. When the email is new, the anonymous row is claimed in place (email set, `email_verified_at` set).
- A single port `MagicLinkDelivery` has a session-flash implementation used in the prototype and a `MailMagicLinkDelivery` stub that throws `LogicException('Email delivery is not implemented yet')`; the binding is selected by `config('magic_links.delivery')`.
- Sign-out links exist on both sites.
- The merge decision is a pure function in `app/Domain/Customers` with sidecar unit tests; the whole flow has HTTP feature tests covering first visit, returning visit, expired link, consumed link, and merge.

## Why it matters
Magic links are the only way into either side of the product. Guest checkout (FEAT-005) depends on "verify email, then continue to the order" working through the `redirect_to` of a link.

## Discovery notes
Read `docs/architecture.md` → Identity.
- Tables (migration timestamps `2026_08_22_0001xx`): `sellers` (email unique, name, shop_name, email_verified_at), `customers` (email nullable unique, name nullable, email_verified_at nullable), `customer_merges` (anonymous_customer_id, customer_id), `magic_links` (token_hash, email, actor_type, redirect_to nullable, expires_at, consumed_at). FEAT-003 is creating commerce tables in parallel with timestamps `2026_08_22_0002xx`; do not create those.
- Guards: add `seller` and `customer` guards (driver `session`) with `sellers` / `customers` Eloquent providers in `config/auth.php`. Models `Seller` and `Customer` extend `Authenticatable`. Middleware aliases `auth.seller`, `auth.customer`.
- Token: 40 random bytes, hex; store `hash('sha256', token)`; URL `/auth/magic/{token}`. Pure core candidates: `MagicLinkPolicy::isUsable(link, now)`, `CustomerMerge::plan(anonymous, existing)` returning which tables to re-point.
- Middleware `ResolveCustomerIdentity` (storefront only): read cookie → find customer, following `customer_merges` if needed → else create anonymous row and queue cookie. Expose via a request attribute and a `customer()` helper or view share. After sign-in, the cookie should point at the verified customer.
- The merge action needs to touch tables FEAT-003 owns (favorites, carts, orders, listing_events, notifications). Write the re-pointing as a table-driven update over a list of `[table, column]` pairs so it works once those tables exist; guard each update with `Schema::hasTable` so tests in this ticket pass before FEAT-003 lands.
- Redirect after verification: use the link's `redirect_to` when present and local, otherwise the site's home.
- Routes belong in `routes/auth.php` with names `auth.seller.login`, `auth.seller.send`, `auth.customer.login`, `auth.customer.send`, `auth.magic.verify`, `auth.seller.logout`, `auth.customer.logout`.

## Working

### For FEAT-004 / FEAT-005

- `customer()` — global helper (autoloaded through `composer.json` →
  `autoload.files` → `app/Support/customer.php`). Returns the resolved
  `App\Models\Customer` for the current request, anonymous or verified, and
  `null` on any route the `customer.identity` middleware has not run on. Works
  in controllers and Blade. Backed by `App\Support\CustomerIdentity::current()`,
  which reads a request attribute — nothing is cached across requests.
- Middleware alias `customer.identity` → `App\Http\Middleware\ResolveCustomerIdentity`.
  Already wraps the whole `routes/shop.php` group; any new storefront route
  inside that group gets `customer()` for free.
- Middleware aliases `auth.seller` and `auth.customer` are `Authenticate::using()`
  for the two guards. Guests are sent to `/seller/login` for `seller` and
  `seller/*` paths and to `/login` everywhere else.
- Guards: `auth('seller')->user()` and `auth('customer')->user()`. Default guard
  is `customer`.
- `App\Support\CustomerIdentity::COOKIE` is the cookie name; the class also owns
  the lifetime, the request attribute, and `forgetCookie()`.
- `/account` (`shop.account`) is a placeholder: the verified address and a
  sign-out form. FEAT-005 extends it.
- Sending a visitor to sign in and back: pass `redirect_to` to `/login` as a
  query string or a hidden form field. It rides the link row and is applied
  after verification. `App\Domain\Auth\LocalRedirect::keepIfLocal()` drops
  anything that leaves this origin.

### Routes (`routes/auth.php`)

| Name | Method | Path |
| --- | --- | --- |
| `auth.seller.login` | GET | `/seller/login` |
| `auth.seller.send` | POST | `/seller/login` |
| `auth.seller.logout` | POST | `/seller/logout` |
| `auth.customer.login` | GET | `/login` |
| `auth.customer.send` | POST | `/login` |
| `auth.customer.logout` | POST | `/logout` |
| `auth.magic.verify` | GET | `/auth/magic/{token}` |

### Decisions

- The verification endpoint reads `actor_type` off the link row and branches to
  `SignInSeller` or `SignInCustomer`. `ActorType` carries its own guard name,
  home route name, and login route name, so neither controller holds a mapping.
- `/auth/magic/{token}`, `/login`, and `/logout` are outside the
  `customer.identity` middleware. Running it there would create an anonymous
  customer for a seller clicking a seller link. The verification controller
  reads the cookie through `ResolveCustomerFromCookie` instead, which returns
  `null` rather than creating a row.
- The anonymous customer row survives a merge. `customer_merges` points the old
  id at the new one, and `ResolveCustomerFromCookie` follows it, so a cookie
  held by a second browser tab resolves forward. Merges are single-hop by
  construction: a merge target is always a verified customer, and only anonymous
  customers are ever merged away.
- The re-pointing walks `App\Domain\Customers\CustomerOwnedTables::all()` and
  guards each entry with `Schema::hasTable` and `Schema::hasColumn`. FEAT-003's
  tables landed while this ticket was in flight, so the merge test replaces
  `favorites` with a two-column stand-in — the commerce schema has columns this
  ticket cannot fill, and the point under test is the walk, not their schema.
- `email_verified_at` is set on a customer that already carries the address but
  has never verified it. That is the guest-checkout row FEAT-005 will write.
- `LocalRedirect` rejects protocol-relative URLs, backslash-escaped paths, and
  anything holding a control character, on top of the same-origin check.
- The stock `users` table, `User` model, `UserFactory`, and
  `password_reset_tokens` are gone. `0001_01_01_000000_create_users_table.php`
  became `0001_01_01_000000_create_sessions_table.php` and creates only
  `sessions`. `DatabaseSeeder` no longer seeds a user; FEAT-006 owns seeding.

### Deviations from the ticket

- Added `App\Support\CustomerIdentity` and `app/Support/customer.php` outside
  the paths the ticket listed. The cookie contract needed one owner and the
  helper needs a file the autoloader can `require`.
- Added `app/Http/Controllers/Shop/AccountController.php` and
  `resources/views/shop/account.blade.php` — the verified customer needs a
  landing page before FEAT-005 exists.
- Added `RefreshDatabase` to FEAT-001's `StorefrontControllerTest`: every
  storefront request now resolves a customer identity, which needs a row.
- `composer.json` gained one `autoload.files` entry. `composer.lock` is
  untouched.
- The pure merge decision is `CustomerIdentityPlan::decide()` rather than the
  ticket's `CustomerMerge::plan()` — `CustomerMerge` is the Eloquent model, and
  the decision covers claim-in-place and sign-in as well as merge.

### Tests

92 tests for this ticket, plus FEAT-001's 20, all green. The full suite was red
at commit time from FEAT-003's in-progress files
(`app/Actions/{Cart,Escrow,Fulfillment,Listings,Notifications,Orders}`,
`app/Console/Commands`) — tests written ahead of their implementations. Nothing
in this ticket's files fails.

---
id: FEAT-010
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-010: Minimal admin actor and site with customer blocks

## Problem
The PHP prototype has two actors and two sites — `seller` and `customer` guards in `src/config/auth.php`, `App\Domain\Auth\ActorType` with two cases, `routes/seller.php` and `routes/shop.php`. Messaging needs a third: two of the four conversation kinds (`admin_seller`, `admin_customer`) name an admin as a participant, and the storefront's "a blocked customer may read but not post" rule needs somebody who can block. There is no `admins` table, no admin guard, no admin site, and no moderation state anywhere in the tree.

## Goal
An admin can sign in at `/admin`, read the sellers and customers on the platform, and block or unblock a customer, and a blocked customer is refused at every point of sale.

## Outcome
- `GET /admin/login` accepts an email; an address with a seeded admin record receives a magic link and reaching that link lands on `/admin`; an address with no admin record is told the same thing but no link is issued and no admin record is created.
- `/admin` and everything under it redirects an unauthenticated visitor to `/admin/login`; a signed-in seller or customer reaching `/admin` lands there too.
- `GET /admin` lists links to the sellers and customers pages.
- `GET /admin/sellers` lists every seller; `GET /admin/sellers/{id}` shows one seller with their listing and fulfillment counts.
- `GET /admin/customers` lists every customer with their standing; `GET /admin/customers/{id}` shows one customer with their orders and current standing.
- A "Block" form on the customer detail page takes a reason and records the block; the page then shows the block with its reason and offers "Lift block", which clears it. Blocking an already-blocked customer and lifting a block on an unblocked one are both refused with a message on the page.
- A blocked customer adding to cart, submitting checkout, or paying an order lands back on the page they submitted from with a message naming the block. Browsing, searching, and favoriting still work for them.
- A magic link issued for a customer or a seller is never followed to a `/admin` path, and an admin's link is never followed to a `/seller` path.
- Every new class has a sidecar test; `make check` is green.

## Why it matters
Every later messaging ticket needs an admin participant and the blocked-customer standing. Without this, half the conversation kinds cannot exist and the storefront's read-only thread has nothing to key off.

## Discovery notes
- Read `docs/messaging.md` § "What a block does" first — it fixes the scope of the admin site (dashboard, sellers, customers, blocks; no listing removals, no analytics, no accounting).
- `admins`: `id`, `email` (unique), `name`, `email_verified_at` nullable, timestamps. Seeded only — no create route. Whether the model carries `email_verified_at` at all is yours to decide; symmetry with `sellers` argues for it.
- `customer_blocks`: `id`, `customer_id` (FK, cascade), `reason` (string), `lifted_at` (timestamp, nullable), timestamps. Index `(customer_id, lifted_at)`. "At most one active block" is the action's rule, not a partial unique index — SQLite portability.
- `Admin` extends `Illuminate\Foundation\Auth\User`, uses `Notifiable` (FEAT-014 sends it notifications) and `HasFactory`. `config/auth.php` gains an `admin` session guard over an `admins` provider. `bootstrap/app.php` gains an `auth.admin` alias (`Authenticate::using('admin')`) and a third branch in `redirectGuestsTo`.
- `ActorType` gains `case Admin = 'admin'`. `allowsPath()` currently knows one prefix; it now needs to keep each actor on their own site — an admin belongs under `/admin` and nowhere a session they lack would wall them, a customer and a seller belong off `/admin`. Extend `LocalRedirect`'s test coverage with the new pairings.
- `AppServiceProvider::boot()` enforces the morph map from `App\Domain\Notifications\RecipientType`. Adding `admin` there is what lets an admin be `Notifiable` and (in FEAT-011) a message sender. Whether `RecipientType` and `ActorType` stay two enums or fold into one is yours — they now hold the same three words.
- Admitting only an existing admin email: the natural Laravel spot is a rule on the send request (`Rule::exists('admins', 'email')`) rather than an `if` in the controller. The response must not reveal whether the address exists.
- The admin site needs its own Blade layout (`components/layouts/admin.blade.php`) beside the two that exist, and its own route file `routes/admin.php` required from `routes/web.php`.
- `Customer::canShop()` is the predicate the refusals read. `AddToCart`, `PlaceOrder`, and `FinalizeOrder` throw `App\Domain\DomainRuleViolation` when it is false — `bootstrap/app.php` already turns that into `back()->withInput()->withErrors(...)` for every route, so no controller gains a branch.
- `tests/Pest.php` binds sidecar directories to base classes; `app/Http/Controllers/Admin` and `app/Http/Requests/Admin` need bindings, and a `Tests\CommerceTestCase` (or a new admin base) helper that signs in an admin. `tests/Arch.php`'s `laravel` preset `ignoring` list may need the block controller if its methods are verbs.
- Risk: `Model::shouldBeStrict()` is on outside production — every admin page must select the columns it renders and eager-load what it walks.

## Related work
- FEAT-002 (magic-link identity) is the sign-in flow this extends. FEAT-011 depends on it.

## Working

Re-validated: the problem still applies — no `admins` table, no admin guard,
no admin site, no `customer_blocks`, no `canShop()` on `Customer` at the start
of this ticket.

### Decisions

- **A third controller, not a generalized one.** `AdminLoginController` mirrors
  `SellerLoginController` (no `redirect_to`, since the admin site has nothing
  like guest checkout to resume). All three login controllers stay in
  `App\Http\Controllers\Auth` / `App\Http\Requests\Auth`, matching where
  `SellerLoginController` and `CustomerLoginController` already live —
  `ActorType` stays the one place that knows the three sites' route names and
  path prefixes.
- **Admits-only-existing-admin without a failing validation rule.**
  `Rule::exists('admins', 'email')` would fail validation for an unknown
  address, which is a different response (422/back with `$errors`) than the
  "check your email" flash a known address gets — that difference is exactly
  what "must not reveal whether the address exists" rules out. Instead
  `SendAdminMagicLinkRequest::admits(): bool` runs the existence check
  (untouched by `rules()`), and `AdminLoginController::send()` only calls
  `SendMagicLink` when it is true; the redirect and flash are identical either
  way. Verified with a same-page assertion for both the known and unknown
  address in `AdminLoginControllerTest`.
- **`SignInAdmin` mirrors `SignInSeller`'s `firstOrNew`** rather than refusing
  when no admin row exists. Since `admits()` already gates who ever receives a
  link, this only matters if an admin row is deleted between the link being
  sent and being followed — an edge case the ticket's acceptance criteria
  don't cover, and refusing it cleanly would mean branching
  `MagicLinkVerificationController`'s uniform three-way `match` on a nullable
  return from one arm only. Symmetry with the other two actors won.
- **`RecipientType` and `ActorType` stay two enums.** `Admin` uses
  `Notifiable`, but nothing in this ticket sends an admin a notification, so
  the morph map (`AppServiceProvider::boot()`) is untouched. FEAT-011/FEAT-014
  is where an admin becomes a notification recipient and that ticket adds the
  `admin` morph alias then.
- **The blocked-purchase message carries the reason**, per
  `docs/messaging.md` ("the shopper lands back on the page they submitted from
  with the reason"): `App\Domain\Customers\CustomerStanding::assertCanShop(?string
  $blockReason)` is a pure function (scalar in, `DomainRuleViolation` out) that
  `AddToCart`, `PlaceOrder`, and `FinalizeOrder` each call against
  `$customer->blockReason()` before doing anything else. `Customer::canShop()`
  stays the boolean predicate `ConversationPolicy` will read in FEAT-011.
- **`Cart` and `CustomerBlock` gained `@property-read Customer $customer`**
  docblocks (matching the one already on `Order`) so PHPStan understands the
  FK is non-nullable, instead of threading `?->` through the three actions for
  a case the schema does not allow.
- **`BlockCustomer` does not take `DateTimeImmutable $now`.** It stamps no
  domain timestamp of its own (Eloquent's own `created_at` is enough), and not
  every action in the tree takes the clock (`RemoveFromCart`,
  `MergeAnonymousCustomer`, `CreateListing` do not either) — only the ones
  that stamp something with it. `LiftCustomerBlock` does take it, for
  `lifted_at`.
- Admin controllers (`SellerController`, `CustomerController`,
  `CustomerBlockController`, `LiftCustomerBlockController`) needed no new
  `tests/Arch.php` ignoring entries: the two list/show controllers stay inside
  the REST vocabulary, and the two write controllers are single-`__invoke`
  classes, the same shape as `ListingStatusController`/`ShipmentController`.

### Verification

`make check` (lint → PHPStan level max → full Pest suite): **810 tests
passed, 1788 assertions**, 0 PHPStan errors, Pint clean on 356 files (up from
the 733 tests/1643 assertions baseline this ticket started from).
`tests/SidecarsTest` confirms every new class has a sidecar.
`php artisan route:list` confirms all ten `/admin*` and `/admin/login`
routes register under `auth.admin`.

Manually re-ran `tests/Arch.php` directly (`vendor/bin/pest tests/Arch.php`):
10 passed, 81 assertions, including the extended `laravel` preset ignoring
list with `AdminLoginController` and the domain-purity/strict-types rules
against every new class — all green.

### Found, not fixed

- `tests/Arch.php` is not discovered by `composer test` / `make check`.
  `phpunit.xml`'s `tests` directory testsuite only matches `*Test.php`, and
  the file is named `Arch.php`, so `vendor/bin/pest` (no path argument) never
  runs it — confirmed with `vendor/bin/pest --filter "the domain core"`
  ("No tests found") versus `vendor/bin/pest tests/Arch.php` (10 passed). This
  predates this ticket (verified `phpunit.xml` untouched in this diff) and is
  a repo-wide test-harness gap, not specific to the admin site this ticket
  adds — every arch rule still passes when run directly, including the ones
  this ticket's changes are held to, but the gate as configured is not
  actually enforcing them on every `make check` run. Fixing the discovery
  config is out of this ticket's scope since it could surface unrelated
  pre-existing violations across the whole tree.

## Review

Reviewed 2026-08-23 against the Outcome list, `docs/architecture.md`, and
`docs/messaging.md` § "What a block does". Walked the live site as well as the
suite: signed in through `/admin/login` with a hand-created admin row, then
`/admin`, `/admin/sellers`, all four seller detail pages, `/admin/customers`
and the customer detail page all answered 200 against `make fresh` data under
`Model::shouldBeStrict()`, and blocking, double-blocking, lifting, and
double-lifting each landed back on the customer page with the right message.

Changed:

- **`tests/Arch.php` now runs in the gate.** `phpunit.xml`'s testsuites match
  `*Test.php` only, so the ten arch rules never ran under `make check`. The
  `App` suite gains `<file>tests/Arch.php</file>`; all ten pass, nothing this
  ticket added violates them.
- **`SignInAdmin` refuses an address with no admin row.** `firstOrNew(...)
  ->save()` would have created an admin for any verified link that reached it
  — the one path where the platform's own account list could grow by itself.
  It now reads `firstOrFail()`, so a link that outlives the row it was issued
  for answers 404. Covered by a sidecar test (no row created, guard still
  guest) and an HTTP test that deletes the admin between issue and follow.
- **The two responses `/admin/login` gives are identical byte for byte** —
  asserted under `mail` delivery, which is what a deployment runs.
- **`App\Http\Controllers\Admin\AdminController` deleted.** Its `admin()` was
  never called (no admin page scopes anything by who is reading), which left
  the file at 0% and the suite at 99.9% against the documented 100%. The four
  admin controllers extend `App\Http\Controllers\Controller` directly;
  coverage is back to 100.0%. FEAT-011 can add the base back when a caller
  exists.
- **HTTP coverage for the other two points of sale.** The blocked-customer
  refusal had an HTTP test for add-to-cart only; checkout (lands on the cart,
  no order) and `/orders/{order}/pay` (lands on the pay page with the reason,
  no payment row) now have one each. Plus a signed-in customer reaching
  `/admin`, which the Outcome names and only the seller case covered.
- **Docs carry the third site.** `docs/architecture.md` gained the admin row
  in the Sites table, the block flow, `routes/admin.php`, the `admin`
  `actor_type`, the Pest bindings, the `tests/Arch.php` discovery note, and
  fresh gate numbers; `docs/data-model.md` gained `admins` and
  `customer_blocks`; `README.md` the new counts.

`make check`: Pint clean on 356 files, 0 PHPStan errors at level max, **826
tests passed, 1887 assertions** (from 810/1788). `make coverage`: 100.0%.

### Review — found, not fixed

- `BlockCustomer` and `LiftCustomerBlock` raise `DomainRuleViolation`
  themselves; they are the only two classes outside `app/Domain` that do, and
  `docs/architecture.md` puts a domain `if` in the core. Folding both into
  `CustomerStanding` costs `LiftCustomerBlock` the narrowed `$block` it needs
  right after the check, which PHPStan at level max would then need helping
  through — larger than a review edit. A follow-up could move both onto the
  model instead (`Customer::block()`, `Customer::liftBlock()`), which is the
  shape `Listing::sell()` already uses.
- Under the prototype's default `session` delivery, `/admin/login` flashes the
  link to the debug alert for an admitted address, so the rendered page does
  differ for an address that has an admin row. That alert is the only way to
  sign in without a mailbox, so it stays; the byte-for-byte guarantee is
  asserted under `mail`.
- `make fresh` seeds no admin, so a live `/admin` walk needs a row created by
  hand until FEAT-015 adds the seeder. `README.md` already points at
  `/admin`.

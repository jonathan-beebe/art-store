---
id: FEAT-014
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-014: Admin messaging inbox and threads

## Problem
The admin site from FEAT-010 reads sellers and customers but cannot talk to them, and the `admin_seller` and `admin_customer` threads that FEAT-012 and FEAT-013 open from the other side have no admin-facing page. Support requests from either site currently reach a thread nobody can read.

## Goal
An admin reads and answers every support thread and starts one with any seller or customer from their detail page.

## Outcome
- `/admin/messages` lists the admin's threads newest first with who each is with, what it is about, and the unread count.
- Opening a thread shows the messages and a reply box, and clears the unread count.
- "Message seller" on a seller's detail page and "Message customer" on a customer's detail page each open the thread with that party; a second use lands on the same thread.
- A support thread a seller or a customer opened appears in the admin inbox and can be answered there, and the answer appears on the other party's thread page.
- A Messages link with the unread count is in the admin nav on every page.
- A conversation id for a thread the admin is not in and an id that matches nothing both answer 404, on the thread page and on the reply.
- Every new class has a sidecar test; `make check` is green.

## Why it matters
Support is a round trip. Two kinds of conversation exist only to reach an admin, and until the admin can answer, both are one-way.

## Discovery notes
- Routes go in `routes/admin.php`, behind `auth.admin`:

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/admin/messages` | `admin.messages.index` | Inbox |
| GET | `/admin/messages/{conversation}` | `admin.messages.show` | Thread; marks it read |
| POST | `/admin/messages/{conversation}` | `admin.messages.store` | Reply |
| POST | `/admin/sellers/{seller}/messages` | `admin.sellers.messages` | "Message seller" from the seller page |
| POST | `/admin/customers/{customer}/messages` | `admin.customers.messages` | "Message customer" from the customer page |

- The inbox, the thread page, and the reply are the same three pages the other two sites render — the pairing differs, the page does not. Look hard at what FEAT-012 and FEAT-013 built before writing a third copy: a shared Blade component for a thread and an inbox row, or a shared view model, is the cheaper answer. Which of the three sites owns it is yours **(decided at build time)**; a third divergent copy is the outcome to avoid.
- The admin holds a real guard, so `$this->authorize(...)` and `@can` work here the way they do in the seller portal — the storefront's `authorizeVisitor` shape is not needed.
- The two "message X" routes open the thread and redirect to it. They post a body or they do not; if they do, they take a `MessageBody` through a form request like every other write.
- `AdminLayoutComposer` bound to `components.layouts.admin` carries the nav count, matching the other two.
- The admin is `Notifiable` from FEAT-010, so a seller's or a customer's reply already leaves the admin a notification row. An admin notifications page is out of scope — the badge and the inbox are the admin's signal.

## Related work
- FEAT-010, FEAT-011, FEAT-012, FEAT-013.

## Working

Re-validated: no route under `routes/admin.php` reached the messaging tables
before this ticket; `admin.messages.*`, `admin.sellers.messages`, and
`admin.customers.messages` all had to be added.

### Decisions

- **The seller portal owns the shared markup; the admin site consumes it.**
  `docs/architecture.md`'s Sites table already gives the seller portal and the
  admin site the same theme (stock Tailwind, system font, dense/tool-focused).
  Diffing FEAT-012's and FEAT-013's inbox/thread views confirmed the seller
  and admin classes are identical strings, so three anonymous components —
  `resources/views/components/messaging/inbox.blade.php`,
  `.../messaging/thread.blade.php`, `.../messaging/body-form.blade.php` — now
  hold that markup once, taking the route names and the `ActorType` viewer as
  props. `seller/messages/index.blade.php` and `.../show.blade.php` were
  rewritten to call them (same rendered HTML, confirmed by the unchanged
  seller message tests passing with no edits), and `admin/messages/*` and the
  "Message seller"/"Message customer" forms on the seller/customer detail
  pages call the same three. The storefront's theme genuinely differs (bright
  vs. dense) and keeps its own hand-styled views — two divergent looks for two
  real themes, not three copies of one.
- **`Admin\AdminController`** added, mirroring `SellerController`: `admin()`
  reads `auth('admin')->user()`. Only the messaging controllers extend it;
  `DashboardController`, `SellerController`, `CustomerController`,
  `CustomerBlockController`, `LiftCustomerBlockController` still extend
  `Controller` directly, since none of them scope by the reading admin.
  `docs/architecture.md`'s base-controller paragraph updated to say so.
- **`admin.messages.show`/`store` use `$this->authorize()`/`Gate::inspect()`**
  exactly like the seller portal's — the admin guard makes `@can` and
  `$this->authorize()` work directly, so `Admin\PostMessageRequest` is a
  near-duplicate of `Seller\PostMessageRequest`. Left as two classes rather
  than a shared base: every site in this codebase already carries its own
  `Http/Controllers/<Site>` and `Http/Requests/<Site>` tree
  (`SellerController`/`ShopController`/now `AdminController`), and a ~30-line
  form request duplicated across two sites that both use the real-guard shape
  is the established cost of that convention, not a new one.
- **"Message seller"/"Message customer" post a body and redirect to the
  thread**, per the brief resolving the ticket's own "(decided at build
  time)": `SellerMessageController`/`CustomerMessageController` open the
  `admin_seller`/`admin_customer` conversation for the *signed-in* admin (not
  `Admin::platformAdmin()` — the admin composing the message knows who they
  are), then `PostMessage` the typed body, then redirect. Both share one
  `Admin\SendMessageRequest` (body-only, `authorize()` left at the
  `FormRequest` default `true`): there is no conversation yet to authorize a
  write against the way a reply does, and the admin guard already settled who
  may reach the route — the same reasoning `AskSellerRequest` uses for a
  domain check that can run before a row exists, applied here to "nothing to
  check yet" instead.
- **Watch-item #1 held by construction**: `AdminLayoutComposer` calls
  `Message::unreadInInboxOf($admin)` — the one scope FEAT-013's review
  already centralized — not a new `whereHas` chain. `Conversation
  ::withUnreadCountFor`, `counterpartName()`, and `ActorDisplay` are reused
  unchanged by the admin inbox and thread.
- **Watch-item #2 resolved by removing the guard.** `admin.messages.show` now
  registers, so every `ActorType::conversationRouteName()` always resolves;
  `NotifyOfMessage`'s `Route::has(...) ? route(...) : null` branch was dead
  the moment this ticket's routes landed. Removed it — `NotifyOfMessage` now
  calls `route(...)` unconditionally. `NotifyOfMessageTest`'s "leaves the url
  null" case became an assertion on the real `admin.messages.show` URL,
  mirroring the seller and storefront cases already there. `NotificationMessage
  ::messageReceived`'s `?string $url` parameter stays nullable — its own test
  exercises `null` directly, independent of `NotifyOfMessage`, and
  `itemSold`/`orderShipped` always pass `null` by design — only its comment
  (which cited the now-stale reason) was reworded.
- **Watch-item #3 confirmed moot**: `AdminLayoutComposer` is new, so no
  existing `expectsDatabaseQueryCount` assertion needed adjusting.
  `SellerMessageControllerTest`/`MessageControllerTest`/`CustomerMessageControllerTest`
  add no queries to `SellerLayoutComposer`'s path; the seller inbox's pinned
  six-query test was not touched and still passes unchanged.

### Verification

`make check` (Pint → PHPStan level max → full Pest suite): Pint clean, 0
PHPStan errors, **1083 tests passed, 2397 assertions** (from the 1049/2322
baseline). `make coverage`: 100.0%. `tests/SidecarsTest` passes with every new
class covered. `php artisan route:list --path=admin` confirms all five new
route names register under `auth.admin` with the exact names and methods
`docs/messaging.md`'s route table specifies.

### Found, not fixed

- No `auth.admin` analogue of `GuardedRoutesTest`
  (`App\Http\Controllers\Seller\GuardedRoutesTest`,
  `App\Http\Controllers\Shop\GuardedRoutesTest`) exists — admin routes are
  each covered by a per-controller "sends a guest to the admin login page"
  test instead, a gap that predates this ticket. The five new admin routes
  follow that same per-controller convention rather than introducing the
  derived test only for themselves.
- `admin.events` (the admin's live-badge stream) is out of scope per this
  ticket's own route table, which lists only the five messaging routes.

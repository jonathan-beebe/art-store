---
id: RFCTR-005
type: refactor
status: resolved
created: 2026-08-23
---

# RFCTR-005: Domain polish — enums own their questions, value objects own their construction

## Problem
Two `StatusLabel` classes with identical bodies exist (`app/Domain/Shop/StatusLabel.php:11` on strings, `app/Domain/Reports/StatusLabel.php:9` on enums); shop views unwrap enums to feed the string one (`shop/order.blade.php:13,52`, `shop/orders.blade.php:27`). `ListingAvailability::isOnStorefront(ListingStatus)` and `OrderPayment::awaitsPayment(OrderStatus)` branch on a single enum from outside it. `FinalizeOrder.php:32-35` holds the rule "a retry after a decline re-takes stock" as a `match` in coordination. Six value objects use public constructors (`CartLine`, `ListingDraft`, `Purchaser`, `ShippingAddress`, `DailyActivity`, `ListingStatusCount`) while eleven use private constructors with named factories; `CartLine` validates inside its public constructor. Fifteen static-only classes (`EmailAddress`, `LocalRedirect`, `MagicLinkToken`, `CartQuantity`, `CustomerOwnedTables`, `Fee`, `ListingAvailability`, `ListingSlug`, `ActivityTimeline`, `ListingStatusTally`, both `StatusLabel`s, `CheckoutPurchaser`, `FakeCard`, `OrderPayment`) can be instantiated meaninglessly. `EmailAddress` (`app/Domain/Auth/EmailAddress.php:11`) is named as a type but is a `normalize(string): string` function, and every email in the system stays a string. Sixteen actions are `final class` with per-property `readonly` while six are `final readonly class`; `Notify::deliverByEmail` is `protected` on a final class. `#[\Override]` is used nowhere though 19 sites override a parent declaration (`casts()` on 13 models, `definition()` on 3 factories, `register()` on the provider, `deliver()` on both delivery implementations). `NotificationMessage::withUrl()` has no production caller. `app/Support/customer.php` (composer `files` autoload) has one production caller, `ShopController.php:26`. Three spellings of "now as `DateTimeImmutable`" exist (`ShopController.php:31`; `ShipmentController.php:25`, `ListingActivityController.php:27`, `PayoutController.php:16`), and identity actions call `now()` internally while commerce actions take `$now`.

## Goal
The domain layer reads as one dialect: enums answer questions about themselves, value objects are built only through named constructors, and helper classes cannot be instantiated.

## Outcome
- One status-label mechanism: each status enum has `label()` and both `StatusLabel` classes are gone.
- `ListingStatus::isOnStorefront()` and `OrderStatus::awaitsPayment()` (and the retry-retakes-stock rule) live on their enums or in `OrderPayment`; `FinalizeOrder` has no domain `match`.
- Every value object is constructed through a named constructor; every static-only class has a private constructor.
- `EmailAddress` is either a real value object (named constructor, `__toString`, used at the magic-link and purchaser boundaries) or renamed to say what it is; `docs/ontology.md` matches the choice.
- All actions are `final readonly class`; `#[\Override]` marks the 19 verified override sites; dead code is removed; the global `customer()` helper and its autoload entry are gone.
- One helper produces "now" for the shell and all four controller sites and the identity actions use it; `docs/architecture.md` states the clock rule (commerce actions receive `$now`; the shell produces it in one place).
- Tests and PHPStan stay green.

## Why it matters
These are the "PHP in 2026" idioms the brief names; a reviewer scanning `app/Domain` should find no older shape.

## Discovery notes
- `#[\Override]` is invalid on `Command::handle()`, `Seeder::run()`, `ServiceProvider::boot()`, `FormRequest::rules()/messages()` (not declared on the parent); adding it there is a fatal error.
- Promoting `EmailAddress` to a VO touches `Purchaser::$email`, `SendMagicLink`, and the `magic_links.email` column; the rename is the smaller change. Either is acceptable; say which in Working notes.
- `CheckoutPurchaser` is handled by RFCTR-003.

## Related work
- RFCTR-003
- RFCTR-004

## Working

### Status labels

`label(): string` is a one-line method on `ListingStatus`, `OrderStatus`, and
`FulfillmentStatus` — the same `ucfirst(str_replace('_', ' ', $this->value))`
body the two deleted `StatusLabel` classes held. Three one-liners rather than a
shared trait: a trait for one expression is `StatusLabel` again under a
different keyword, and the Outcome asks for the method on the enum.

Those three are the only status enums a view renders. `PaymentStatus`,
`LedgerEntryType`, `ListingEventType`, `DeclineReason`, `ActorType`,
`MagicLinkStatus`, `RecipientType`, `CustomerIdentityAction`, `FavoriteChange`,
and `PaymentOutcome` never reach a page as text, so none gained `label()`.

Six Blade files dropped their `@use` and call `$status->label()`;
`Seller\OrderController`, `Seller\ListingStatusController`, and
`ListingStatusCount::label()` do the same.

### Where each question moved

| Question | Was | Now |
| --- | --- | --- |
| Does this listing have a public page? | `ListingAvailability::isOnStorefront($status)` | `ListingStatus::isOnStorefront()` |
| Can a card still carry this order to paid? | `OrderPayment::awaitsPayment($status)` | `OrderStatus::awaitsPayment()` |
| Does a retry have to take the stock again? | `match` in `FinalizeOrder` | `OrderStatus::retakesStockOnRetry()` |
| Can this listing be bought right now? | `ListingAvailability::isPurchasable($status, $quantity)` | unchanged — two inputs |
| May this person pay this order? | `OrderPayment::isPayableBy($status, $verified)` | unchanged — two inputs |

The retry rule went on the enum rather than into
`OrderPayment::retakesStockOnRetry(OrderStatus)` as the guidance offered: it
reads one enum and nothing else, which is the shape this ticket is removing
everywhere else. `OrderPayment` and `ListingAvailability` each keep exactly the
one two-input predicate that needs a home outside an enum.

`FinalizeOrder` keeps `match ($outcome)` over `PaymentOutcome::Approved` /
`Declined`. That is BUG-002's deliberate two-case dispatch — it chooses which
persistence step runs, and it is statically exhaustive. The Problem's target
was the `match ($order->status)` on line 32, which is gone; it is now
`if ($order->status->retakesStockOnRetry())`, computed before the transaction
opens so the closure captures a bool instead of re-reading the model.

### `EmailAddress` → `EmailNormalizer` (renamed, not promoted)

Renamed. Promotion touches `Purchaser::$email`, `SendMagicLink`, both login
controllers, `CheckoutController`, `PlaceOrder`, `MagicLinkVerificationController`,
`SendMagicLinkRequest`, `CheckoutRequest`, their four sidecars, and the
`orders.email` / `magic_links.email` columns — 17-plus files against the
guidance's ~10 ceiling, and the `email` column stays a string on both tables
either way, so the value object would be unwrapped at every write. The class is
one `normalize(string): string` function; `EmailNormalizer` says that.

`docs/ontology.md`'s Magic link entry names it and states the rule (an address
stays a `string` end to end; one place lowercases and trims it).

### Constructors

Named constructors added, constructor made private: `CartLine::of()` (the
`quantity < 1` guard moved out of the constructor into the factory),
`ListingDraft::of()`, `DailyActivity::on()`, `ListingStatusCount::of()`.
`Purchaser` had `forCheckout()` but a public constructor; it gained
`onAccount()` for the three callers that build from an account already read
(`CommerceTestCase`, `OrderHistorySeeder`, the domain sidecars) and its
constructor is private.

Private constructors on every static-only class: `EmailNormalizer`,
`LocalRedirect`, `MagicLinkToken`, `CartQuantity`, `CustomerOwnedTables`,
`Fee`, `ListingAvailability`, `ListingSlug`, `ActivityTimeline`,
`ListingStatusTally`, `FakeCard`, `OrderPayment`, plus two the ticket's list
did not name because they sit in `app/Support`: `CustomerIdentity` and
`PlaceholderImage`. `StatusLabel` (both) and `CheckoutPurchaser` were already
gone. A sweep over `app/` confirms no static-only class has a public
constructor left, and the only public constructors remaining are the
dependency-injection ones on actions, middleware, and the delivery adapter.

### `#[Override]`

20 sites, each verified against `vendor/laravel/framework` before it was added:

| Site | Parent declaration |
| --- | --- |
| `casts()` on 13 models | `HasAttributes::casts()`, inherited through `Model` |
| `definition()` on 3 factories | `Factory::definition()` (abstract) |
| `register()` on `AppServiceProvider` | `ServiceProvider::register()` |
| `deliver()` on both delivery implementations | `MagicLinkDelivery` interface |
| `messages()` on `ListingRequest` | `FormRequest::messages()` |

`messages()` is the 20th, past the ticket's count of 19: `FormRequest` declares
it at `FormRequest.php:372`. Not added, because the parent does not declare
them: `boot()` on `ServiceProvider`, `rules()` and `authorize()` on
`FormRequest`, `run()` on `Seeder`, `handle()` on `Command`, `handle()` on the
middleware. Pint's `global_namespace_import` rewrote `#[\Override]` to
`use Override;` + `#[Override]`.

### The clock

`Controller::now(): DateTimeImmutable` on the base controller; the identical
methods on `SellerController` and `ShopController` are gone. Four controllers
that read `now()` directly now call it: both notification-read routes,
`MagicLinkVerificationController`, and the two login controllers.

The four identity actions take `DateTimeImmutable $now` last, like the commerce
actions: `SendMagicLink` (its `?string $redirectTo` lost its default so `$now`
stays last; `SellerLoginController` passes `null`), `SignInSeller`,
`SignInCustomer`, `ClaimCustomerIdentity`. `SendMagicLink` computes
`expires_at` as `$now->add(new DateInterval("PT{$minutes}M"))` rather than
Carbon's `addMinutes`.

Two model methods took the type with them: `Notification::markRead($at)` (new,
replacing `->update(['read_at' => now()])` in two controllers) and
`MagicLink::statusAt()` / `consume()`, widened from `Carbon` to
`DateTimeImmutable`.

`RunWeeklyPayouts` still calls `now()`. A console run has no controller, and
BUG-002 made that branch the tested fallback for a missing `--as-of`.
`docs/architecture.md` gains a **The clock** subsection stating the rule and
naming that exception.

### Dead code

- `NotificationMessage::withUrl()` removed. It had no production caller — only
  three tests, which now assert `url` is null. Nothing writes
  `notifications.url` today; the column stays for RFCTR-007.
- `app/Support/customer.php` and the composer `files` autoload entry deleted
  (`composer dump-autoload` run in the container). The ticket named one caller;
  there were four by the time this ticket ran — `ShopController::visitor()`,
  `ShopRequest::visitor()` (RFCTR-003), the `@visitorCan` directive in
  `AppServiceProvider` (RFCTR-002), and one middleware test. All four call
  `CustomerIdentity::current()`.
- `Notify::deliverByEmail` is `private`.

### All 20 actions are `final readonly class`

The 14 that were `final class` with per-property `readonly` lost the per-property
keyword. Every action holds only injected collaborators, so nothing blocked it.

### Numbers

- Tests: 620 → 630 (1431 → 1440 assertions), full suite green.
- PHPStan: 3 → 2. `OrderStatus::fromFulfillments` is fixed — all three counts
  are `int` locals now, so the `int<0, max> === *NEVER*` comparison is gone.
  The two left are the generic-key return types in `DashboardController` and
  `ListingActivityController`; both are `Collection::countBy()->all()` losing
  the string key, and RFCTR-006 moves both aggregations database-side. Left
  alone rather than annotated, to keep out of that ticket's way.
- Pint: clean, 286 files.

### Files touched that the ticket does not name

- `app/Http/Controllers/Controller.php` — `now()` hoisted here.
- `app/Models/Notification.php` + sidecar — `markRead()`.
- `app/Models/MagicLink.php` + sidecar — the `Carbon` → `DateTimeImmutable`
  widening the clock rule forced.
- `app/Http/Controllers/Shop/CheckoutController.php` — passes `$now` to
  `SendMagicLink`.
- `app/Http/Requests/Shop/ShopRequest.php`, `app/Providers/AppServiceProvider.php`,
  `app/Http/Middleware/ResolveCustomerIdentityTest.php` — the three
  `customer()` callers the ticket did not know about.
- `app/Actions/Auth/SignInSeller.php` — `$seller->email_verified_at ??= $now`
  became a `forceFill`; assigning a `DateTimeImmutable` straight onto a
  `Carbon`-cast property is a PHPStan `assign.propertyType` error, and the fill
  goes through the cast.
- `docs/architecture.md` (Core layer rules, base-controller paragraph, the new
  **The clock** subsection, suite count 620 → 630), `docs/ontology.md`
  (magic link, the three status entries), `docs/identity.md` (the `customer()`
  question), `composer.json`.

### Left out

- `FinalizeOrder`'s `match ($outcome)` — see **Where each question moved**.
- The two PHPStan errors in `DashboardController` / `ListingActivityController`
  — see **Numbers**.
- `ListingDraft` and `ListingStatusCount` stay on `tests/SidecarsTest.php`'s
  exception list. Both are still data holders whose one behavior
  (`attributes()`, `label()`) is asserted through the file that builds them.

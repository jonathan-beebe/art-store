---
id: RFCTR-005
type: refactor
status: open
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

---
id: RFCTR-004
type: refactor
status: open
created: 2026-08-23
---

# RFCTR-004: Cohesive models, relations, and money accessors

## Problem
The write that keeps the listing `quantity`/`status` invariant is spread over three byte-similar private methods (`app/Actions/Orders/PlaceOrder.php:79-86`, `FinalizeOrder.php:85-92`, `:94-101`), and `ChangeListingStatus` (`app/Actions/Listings/ChangeListingStatus.php:10-15`) is a one-line wrapper around `$listing->update([...])`. `app/Models/Customer.php` defines no relations, so six call sites write the foreign key by hand (`Shop/OrderController.php:14-18`, `Shop/AccountController.php:18-21`, `Shop/ShopController.php:43-46`, `Shop/ListingController.php:24-27`, `Actions/Favorites/ToggleFavorite.php:18-21`, `Actions/Cart/CurrentCart.php:17-23`); `Shop/FavoriteController.php:16-22` uses a `whereIn` subquery though `Listing::favorites()` exists; `Seller/OrderController.php:47-48` drops to `->getQuery()`; `payments()->orderByDesc('id')->first()` is repeated in two controllers where `latestOfMany()` fits; `Listing` has no `orderItems()` (`Seller/ListingActivityController.php:50-57`). "Is this customer verified" is written out three times (`CheckoutController.php:83`, `Shop/OrderController.php:25`, `OrderPaymentController.php:48`) beside `Customer::isAnonymous()`. `CurrentCart` is the only query under `app/Actions` and is constructor-injected into every storefront controller via `ShopController::__construct`, run twice per request in `CheckoutController::show`. `Money` exposes `cents` and callers do arithmetic on it (`app/Domain/Escrow/Fee.php:18`, `LedgerBalance.php:13`); `Order`, `Fulfillment`, `OrderItem`, `Payment` lack `Money` accessors so eight Blade expressions call `Money::fromCents($model->x_cents)` (`shop/order.blade.php`, `shop/orders.blade.php`, `shop/pay.blade.php`, `seller/earnings.blade.php`, `seller/orders/show.blade.php`, `seller/listings/show.blade.php`); `PayoutSummary::of` takes `list<int>` while `Payout::amount()` sits unused (`Seller/PayoutController.php:18`). `Notify` builds a dynamic-key array via the public `Notification::recipientColumn()` (`app/Actions/Notifications/Notify.php:13-18`). `MagicLink.php:34-37` uses the legacy `scopeForToken` prefix while every other model uses `#[Scope]`.

## Goal
Each model owns the writes and reads that belong to it, so actions sequence model methods rather than restating column names.

## Outcome
- `Listing` owns selling, restocking, and status changes; actions call those methods and the three stock helpers are gone; the listing invariant has one write site.
- `Customer` has relations for orders, carts, favorites, favorite listings, notifications, and listing events, plus `isVerified()` and a current-cart method; the hand-written foreign-key queries and the `CurrentCart` constructor injection are gone.
- `Order::latestPayment()`, `Listing::orderItems()` exist and are used; `Seller/OrderController` no longer calls `getQuery()`; `FavoriteController` reads through a relation.
- Every `*_cents` column has a `Money` accessor on its model; Blade never calls `Money::fromCents`; `PayoutSummary::of` takes money; `Money` has `equals`, `subtract`, `isPositive` (or equivalents) and `__toString`, and `Fee`/`LedgerBalance` use them.
- `Notification` has a named constructor taking the recipient type, id, and message; `recipientColumn()` is private or lives on `RecipientType`.
- `MagicLink` uses the `#[Scope]` attribute.
- All existing action and controller tests pass; new model tests cover sell/restock/changeStatus and the Customer relations; PHPStan stays clean.

## Why it matters
Thin-but-not-anemic models are the Eloquent idiom; this is where the prototype's domain objects meet Active Record and a reviewer will read both.

## Discovery notes
- Architecture doc's layer table calls models "thin: relations, casts, scopes"; a model method that applies a pure `ListingStock` decision keeps the decision in the core and the write in the adapter. Update `docs/architecture.md` wording to say models own their invariant-preserving writes.
- `Cart::lines()`, `Fulfillment::net()`, `Listing::price()`, `Payout::amount()` are the existing accessor convention.
- `ToggleFavorite` and `AddToCart` keep their action shape (they record events); they just call relations.

## Related work
- RFCTR-003
- BUG-001

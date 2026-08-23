---
id: BUG-001
type: bug
status: open
created: 2026-08-23
---

# BUG-001: Checkout returns 500 when a cart line left the storefront

## Problem
Nothing between the cart and the stock decrement re-checks availability. `app/Http/Controllers/Shop/CartController.php:28-30` guards `ListingAvailability::isPurchasable` on add, but `CheckoutController::place` (`app/Http/Controllers/Shop/CheckoutController.php:42-79`) only checks the cart is non-empty, `CartTotals::forCheckout` (`app/Domain/Cart/CartTotals.php:22-29`) only rejects an empty cart, and `PlaceOrder::takeStock` (`app/Actions/Orders/PlaceOrder.php:79-86`) calls `ListingStock::afterSale`, which throws `DomainException` for any non-`for_sale` status or insufficient quantity (`app/Domain/Listings/ListingStock.php:16-22`). A seller archiving a listing that sits in a cart, or the last unit selling to another customer first, gives the shopper an HTTP 500 mid-checkout. More broadly, no `DomainException` is mapped to an HTTP response anywhere (`bootstrap/app.php:27-30` registers only a JSON rule; no `app/Exceptions`), and controllers answer the same "illegal transition" question three different ways: `ShipmentController.php:19` aborts 422, `DeliveryConfirmationController.php:18` aborts 404, `ListingStatusController.php:21,35-38` validates with `Rule::in`; `CartController.php:28` shadows `CartQuantity::withinStock`'s exception (`app/Domain/Cart/CartQuantity.php:17`) so one path flashes and the other 500s.

## Goal
A rule the domain rejects reaches the user as a message on the page they were on, never as a server error.

## Outcome
- A shopper whose cart holds a listing that was archived, or whose last unit sold to someone else, is returned to the cart with a message naming the unavailable item, and the cart page shows that line as unavailable; no order row and no stock change result.
- Any domain rule violation thrown from an action during an HTTP request renders as a redirect back with the rule's message flashed (422 semantics), and this mapping lives in one place.
- The duplicated pre-flight transition checks in `ShipmentController`, `DeliveryConfirmationController`, and `CartController` are gone; the action's own guard is the single source, and the HTTP tests for those routes still pass with the same status codes they assert today or with the unified behavior, whichever the ticket's Working notes justify.
- Tests exist for: checkout after archive, checkout after last-unit sale, `PlaceOrder` refusing a listing that left the storefront, and the cart page marking an unpurchasable line.

## Why it matters
This is the one reachable 500 in the product's main flow, and the competing prototypes will be judged on the same race.

## Discovery notes
- One exception type in the core (e.g. a `DomainRuleViolation extends DomainException` that the transition/stock/quantity guards throw) plus one `->render()`/`withExceptions` mapping in `bootstrap/app.php` covers every path. Laravel's `Exceptions::render(fn (DomainRuleViolation $e, Request $request) => back()->withErrors(...))` is the idiomatic hook.
- `ListingAvailability::isPurchasable` remains useful for rendering the add-to-cart button; the ticket is about guarding the write.
- Keep the 404-not-403 behavior documented at `ShopController.php:50-53` for ownership; this ticket is about state, not ownership.

## Related work
- MAINT-001
- RFCTR-001

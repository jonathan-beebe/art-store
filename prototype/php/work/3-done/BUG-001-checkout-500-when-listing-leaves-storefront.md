---
id: BUG-001
type: bug
status: resolved
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

## Working

### The one exception type
`App\Domain\DomainRuleViolation extends DomainException` (`src/app/Domain/DomainRuleViolation.php`).
Thrown by `ListingStock::afterSale`, `CartQuantity::withinStock`,
`CartTotals::forCheckout`, and `transitionTo()` on `ListingStatus`,
`OrderStatus`, `FulfillmentStatus`. `CartTotals` was added to the list the
ticket named: an empty cart is the same kind of refusal, and leaving it as a
bare `DomainException` would have left one path able to 500.
`InvalidArgumentException` throws stay as they are — a sale of zero items or a
cart line below one is a caller bug, not something a shopper can trip.

Narrowing keeps every existing `toThrow(DomainException::class)` expectation
valid, and `ListingSearch`'s `DomainException` stays untouched: it guards a
call the storefront never makes with an empty term.

### The one mapping
`bootstrap/app.php`:

```php
$exceptions->render(fn (DomainRuleViolation $violation) => back()
    ->withInput()
    ->withErrors($violation->getMessage()));
```

No view rendered `$errors` before this, so validation failures were silent too;
both layouts now render the bag. That is why the HTTP tests assert the message
through the rendered page rather than through the session: `withErrors(string)`
lands under a numeric key, and asserting on the page is the behaviour anyone
cares about.

### Status codes the HTTP tests assert now
| Route | Was | Now | Why |
| --- | --- | --- | --- |
| `POST /seller/orders/{id}/shipment`, already shipped | 422 | 302 back, message rendered | A form post's answer is the form's page with the reason on it. A seller who double-clicks Ship got a bare 422 error page. |
| `POST /orders/{o}/fulfillments/{f}/delivered`, already delivered | 404 | 302 back, message rendered | The fulfillment exists and is theirs; 404 said otherwise. Covered by a new test — the old 404 had none. |
| `POST /cart/{listing}`, not purchasable | 302 + `session('error')` | 302 + `$errors` | Same shape as every other refusal. |
| `POST /checkout`, cart line left the storefront | 500 | 302 to `/cart`, message names the item | The bug. |
| Any route, row is not the visitor's / not the seller's | 404 | 404 | Ownership, not state. Unchanged. |

### Naming the item
`ListingStock::afterSale` takes the listing title and names it:
`“Harbour at Dawn” is no longer for sale.` The core owns the sentence the
shopper reads; the action supplies the noun. `afterRestock` keeps its old
signature because it has no reachable refusal — a restock either bumps the
quantity or moves `sold → for_sale`, both allowed.

`afterSale`'s status guard became `! ListingAvailability::isPurchasable($status, $quantity)`,
which folds the sold-out case (`for_sale` with 0 left) into the same message
instead of reporting it as a quantity shortfall.

`CartQuantity::withinStock` gained the listing status, so the domain now
answers the whole question `CartController` used to pre-empt. Without it,
removing that guard would have let an archived listing with stock into a cart.

### Where the shopper lands
The global mapping is `back()`, which from `POST /checkout` is the checkout
page. `CheckoutController::place` catches `DomainRuleViolation` and redirects
to the cart instead — that is where the shopper can act on the refusal, and
where `shop/cart.blade.php` marks the line (`Listing::isPurchasable()`, a
delegate to `ListingAvailability::isPurchasable`). The catch chooses a
destination; it re-checks no rule, so the action's guard is still the only one.
Routing by request inside `bootstrap/app.php` was the alternative and puts
route names in the wrong file.

### Left out
- The Checkout button on a cart holding an unavailable line is still live. The
  shopper is refused at the write with the item named, which is the outcome the
  ticket asks for; disabling the button is a follow-up, not a second guard.
- The transition messages a seller reads (`A fulfillment cannot move from
  shipped to shipped.`) are the domain's own wording. Rewriting them per
  transition is out of scope.

### Numbers
- Tests: 485 → 508 (1123 → 1181 assertions), full suite green. 14 of the 23
  new tests are this ticket's: 2 `PlaceOrder`, 2 `CheckoutController`, 2
  `CartController`, 1 `DeliveryConfirmationController`, 1 `Listing`, 2
  `DomainRuleViolation`, 2 `CartQuantity`, 2 `ListingStock`. The rest are
  BUG-002's, running in the same tree.
- PHPStan: 47 → 44 errors. Two came from `CartController`'s removed guard and
  its removed `$listing->quantity` access; the third is BUG-002's fix.
- Pint: clean on every file this ticket touched. The one reported issue is
  BUG-002's `StoreListingImage.php`.

### Files a concurrent agent also holds
`app/Actions/Orders/FinalizeOrder.php` — one line, `takeStock()`'s
`ListingStock::afterSale` call, which the new `$title` parameter forced.
BUG-002 owns that file's `match`/`OrderStatus::fromCardDecision`; the two edits
do not overlap.

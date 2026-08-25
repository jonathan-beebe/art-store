---
id: RFCTR-004
type: refactor
status: resolved
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

## Working

### Where each write now lives

```
ListingStock / ListingStatus (core)      decides the (quantity, status) pair
        └─ Listing::sell() / restock()   applies it, one private applyStock() write
        └─ Listing::changeStatusTo()     applies transitionTo(), writes status
              └─ PlaceOrder, FinalizeOrder, ListingStatusController  sequence only
```

`Listing::applyStock()` is the single site that writes `quantity` and `status`
together. The three byte-similar private helpers are gone: `PlaceOrder` loops
`$item->listing->sell($item->quantity)` inline, `FinalizeOrder` keeps two
three-line helpers (`sellItems`, `restockItems`) because both sit in `match`
arms and iterate the order's items.

### `ChangeListingStatus` is deleted

It wrapped one `update()` around `transitionTo()` and did nothing else, so the
model now holds it and `ListingStatusController` calls
`$listing->changeStatusTo($request->status())` — one less injected action on a
route that already binds the model. Its three tests moved to
`Models\ListingTest` unchanged in substance (the two refusal tests merged into
one that asserts the throw and the untouched row together).

### `CurrentCart` is deleted

`Customer::currentCart()` holds the "cart with the most items, else create"
rule and the comment explaining why a customer can own two. Every storefront
controller lost the constructor injection; `ShopController` has no constructor
at all now. Its three tests moved to `Models\CustomerTest`.

`CheckoutController::show` still resolves the cart twice per request — once for
the page, once inside `page()` for the header count. That is the header's cost,
not `CurrentCart`'s, and it was there before.

### `Notification::for` removed as dead code

Both sites now read notifications through `Seller::notifications()` /
`Customer::notifications()`, so the `for` scope had no production caller left.
`RecipientType::column()` is still what `Notification::to()` asks. `unread()`
stays — `ShopController` and `DashboardController` use it.

### Money

`equals`, `subtract`, `isPositive`, `isZero`, `zero()`, and `__toString`
(delegating to `format()`, with `Stringable`). `zero()` is what
`LedgerBalance::from` accumulates onto and what `PayoutSummary::of` reduces
from, so neither touches `->cents` any more; `Fee::net` is
`$subtotal->subtract(self::platform($subtotal))`; `LedgerBalance::isPayable`
is `$this->available->isPositive()`.

Accessors added: `Order::subtotal()/total()`, `Fulfillment::subtotal()/fee()`,
`OrderItem::unitPrice()/lineTotal()`, `Payment::amount()`,
`LedgerEntry::amount()` (`Listing::price()`, `Fulfillment::net()`, and
`Payout::amount()` already existed). Every `*_cents` column has one. Blade
calls the accessor and lets `__toString` format it, so the six views dropped
their `@use('App\Domain\Money\Money')`.

Actions still write `*_cents` columns from a `Money` (`'fee_cents' =>
Fee::platform($subtotal)->cents`). That is the cast boundary, not arithmetic.

### Relations replacing hand-written foreign keys

| Was                                                                      | Now                                                      |
| ------------------------------------------------------------------------ | -------------------------------------------------------- |
| `Order::query()->where('customer_id', ...)`                              | `$visitor->orders()`                                     |
| `Notification::query()->for(Customer, id)`                               | `$visitor->notifications()`                              |
| `Favorite::query()->where('customer_id', ...)->where('listing_id', ...)` | `$customer->favorites()->firstWhere('listing_id', ...)`  |
| `Listing::whereIn('id', Favorite::select('listing_id'))`                 | `$visitor->favoriteListings()`                           |
| `$seller->fulfillments()->getQuery()`                                    | `Fulfillment::query()->whereBelongsTo($seller)`          |
| `OrderItem::query()->where('listing_id', ...)`                           | `$listing->orderItems()`                                 |
| `$order->payments()->orderByDesc('id')->first()`                         | `$order->latestPayment` (`latestOfMany()`), eager-loaded |

`favoriteListings()` orders by `listings.id`: the join puts two `id` columns in
scope and an unqualified `orderByDesc('id')` is ambiguous.

`whereBelongsTo` rather than the relation itself, because `$seller->fulfillments()`
returns a `HasMany` and the method is typed `Builder<Fulfillment>`; the query
builder keeps the generic honest without `getQuery()`.

### Tests

`tests/Pest.php` binds `../app/Models` (was `../app/Models/ListingTest.php`) to
`CommerceTestCase`, so `MagicLinkTest` dropped its file-local
`uses(TestCase::class, RefreshDatabase::class)` and every model sidecar reads
the same way. Six sidecars added — `Order`, `Fulfillment`, `OrderItem`,
`Payment`, `Notification`, `LedgerEntry` — and `tests/SidecarsTest.php`'s
exception list went from 20 entries to 14.

### Numbers

- Tests: 586 → 620 (1359 → 1431 assertions), full suite green.
- PHPStan: 4 → 3. The one fixed is `ToggleFavorite`'s
  `method.nonObject`, gone with `$favorite?->delete()`. The remaining three
  (`OrderStatus::fromFulfillments`, and the generic-key return types in
  `DashboardController` and `ListingActivityController`) sit in code this
  ticket does not rewrite; RFCTR-006 moves both of those aggregations
  database-side.
- Pint: clean, 291 files.

### Left out

- `seller/listings/form.blade.php` still renders the price field value as
  `number_format($listing->price_cents / 100, 2, '.', '')`. It needs the raw
  editable number, not a formatted amount; a `Money::toDollars()` inverse of
  `fromDollars()` would be the fix and belongs with RFCTR-005's value-object
  polish.
- `PayoutController` still names `Payout` to type the `array_map` closure. The
  map is now `fn (Payout $payout): Money => $payout->amount()`.

### Files touched that the ticket does not name

- `app/Models/LedgerEntry.php` — `amount()`, so no model is left reading
  `*_cents` into `Money` at a call site.
- `app/Actions/Notifications/NotifyTest.php` — the removed `for` scope.
- `tests/Pest.php`, `tests/SidecarsTest.php`.
- `docs/architecture.md` — the Adapters row, the Notifications paragraph, the
  Pest binding list, the suite count (586 → 620).
- `docs/review.md` — `Models\ListingTest` added to the "Manage listings" row.

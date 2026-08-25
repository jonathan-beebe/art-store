---
id: FEAT-005
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-005: Customer storefront with cart, checkout, and guest verification

## Problem
Customers have an anonymous identity and a magic-link login (FEAT-002) and the order lifecycle exists (FEAT-003), but there is no storefront: nothing to browse, favorite, add to cart, or buy.

## Goal
A first-time visitor can browse, favorite, add to cart, check out with a fake card as a guest, verify their email, and watch the order ship and deliver.

## Outcome
- `/`: grid of `for_sale` listings (image, title, artist shop name, price), with a search box (title/description/medium) and medium filter; pagination. Each view of a listing page records a `view` listing event.
- `/art/{listing:slug}`: large image, title, artist, medium, dimensions, description, price, quantity available, Favorite/Unfavorite button, Add to cart button. Sold listings show "Sold" and no cart button.
- `/favorites`: the customer's favorites. `/cart`: items, quantities, remove, subtotal, "Checkout" button.
- `/checkout`: email (prefilled and read-only when signed in), shipping name + address, card number / expiry / CVC (no real validation beyond the fake-card table), Place order. For an unverified email the order is created as `pending_verification`, the page explains that a verification link was "sent", and the debug alert shows the magic link whose `redirect_to` is the order's finalize URL. Clicking it verifies (merging anonymous history per FEAT-002), finalizes the order, and lands on the order page. For a verified, signed-in customer the order is finalized immediately.
- Declined card (`4000 0000 0000 0002`, `4000 0000 0000 9995`, or any other number) shows the decline reason on the order page with a form to retry with another card; `4242 4242 4242 4242` succeeds.
- `/orders` and `/orders/{order}`: status, items grouped by seller with fulfillment status, carrier/tracking once shipped, and a "Confirm delivery" button per shipped fulfillment.
- `/account`: email, sign-in/out, notifications (e.g. "Order shipped") with mark-as-read.
- Cart, favorites, and orders are keyed to the anonymous customer and survive verification/merge (tested).
- Storefront layout: white, airy, large imagery, readable type, brand name small; semantic HTML; no JavaScript. HTTP feature tests beside each controller cover browse/search, listing view event, favorite toggle, cart add/remove, guest checkout through verification to paid, signed-in checkout, declined then retried card, and confirm delivery.

## Why it matters
This is the customer half of the end-to-end test and the only place the payment mock and guest verification are exercised.

## Discovery notes
Read `docs/architecture.md`. Controllers in `app/Http/Controllers/Shop/`, views in `resources/views/shop/`, routes in `routes/shop.php` behind the `ResolveCustomerIdentity` middleware from FEAT-002. Use the FEAT-003 actions (`AddToCart`, `PlaceOrder`, `FinalizeOrder`, `ConfirmDelivered`, `RecordListingEvent`). The card is never stored; pass the number to `FinalizeOrder` and keep only last four. The finalize URL for a pending order should be signed or owned-checked so another customer cannot finalize it. Listing images: `Storage::url(image_path)` with the shared placeholder SVG fallback. Search can be `LIKE` on SQLite.

## Working

### Checkout decision: verification comes before the card

The card number never reaches the database or the session, so an order can
only be charged inside the request that carries the number. That leaves one
question: what does a guest — an anonymous customer with an unverified
address — do at checkout?

```
verified, signed in                unverified guest
───────────────────                ────────────────
POST /checkout                     POST /checkout
  email + shipping + card            email + shipping (no card fields)
  PlaceOrder -> awaiting_payment     PlaceOrder -> pending_verification
  FinalizeOrder(card) -> paid        SendMagicLink(redirect_to=/orders/{id}/pay)
  -> /orders/{id}                    -> /orders/{id} "Check your email"
                                          |
                                     GET /auth/magic/{token}
                                       claim-or-merge (FEAT-002)
                                       -> /orders/{id}/pay
                                          card -> FinalizeOrder -> paid
```

`/checkout` renders card fields only for a verified customer, so a guest is
never asked for a number that would have to be thrown away. The branch is the
domain predicate `OrderPayment::isPayableBy(status, isPurchaserVerified)`, and
both the checkout controller and the payment controller ask it.

`/orders/{order}/pay` is owner-checked rather than signed: `customer()` is
resolved on every storefront request, and after verification the merge has
already re-pointed `orders.customer_id` at the verified customer, so the same
check covers the guest who just verified, the returning customer on another
device (sent to `/login` with `redirect_to`), and a stranger (404).

### Routes (`routes/shop.php`, all behind `customer.identity`)

| Name                              | Method | Path                                                           |
| --------------------------------- | ------ | -------------------------------------------------------------- |
| `shop.home`                       | GET    | `/`                                                            |
| `shop.listing`                    | GET    | `/art/{listing:slug}`                                          |
| `shop.favorites`                  | GET    | `/favorites`                                                   |
| `shop.favorites.toggle`           | POST   | `/art/{listing:slug}/favorite`                                 |
| `shop.cart`                       | GET    | `/cart`                                                        |
| `shop.cart.add`                   | POST   | `/cart/{listing:slug}`                                         |
| `shop.cart.remove`                | DELETE | `/cart/{listing:slug}`                                         |
| `shop.checkout`                   | GET    | `/checkout`                                                    |
| `shop.checkout.place`             | POST   | `/checkout`                                                    |
| `shop.orders`                     | GET    | `/orders`                                                      |
| `shop.order`                      | GET    | `/orders/{order}`                                              |
| `shop.order.pay`                  | GET    | `/orders/{order}/pay`                                          |
| `shop.order.pay.submit`           | POST   | `/orders/{order}/pay`                                          |
| `shop.order.delivered`            | POST   | `/orders/{order}/fulfillments/{fulfillment}/delivered`         |
| `shop.account`                    | GET    | `/account` (`auth.customer`)                                   |
| `shop.account.notifications.read` | POST   | `/account/notifications/{notification}/read` (`auth.customer`) |

### Domain added (pure, sidecar unit tests)

- `Shop\ListingSearch` — the term and medium a visitor asked for, and the LIKE
  pattern for the term. SQLite LIKE has no escape character unless the query
  names one, so a wildcard the visitor typed is dropped rather than escaped.
- `Shop\CheckoutPurchaser::forCustomer(...)` — builds the `Orders\Purchaser`.
  A verified customer buys under the address on their account, so a submitted
  field cannot move an order onto someone else's identity.
- `Shop\StatusLabel::humanize()` — snake_case state to sentence, for the views.
- `Orders\OrderPayment::awaitsPayment()` / `isPayableBy()` — an order awaits
  payment while a card could still carry it to `paid`, which is
  `OrderStatus::canTransitionTo(Paid)`; only a verified purchaser may pay.
- `Listings\ListingAvailability::isOnStorefront()` / `isPurchasable()` — a sold
  listing keeps its page, a draft or archived one was never public; only a
  `for_sale` listing with stock reaches the cart.
- `Favorites\FavoriteChange` — `Added` / `Removed` from the current state, and
  the `ListingEventType` each one records.

### Actions added

- `Cart\CurrentCart` — the visitor's cart, created on first use. A merge can
  leave a customer holding two carts; the one with items is the one they were
  shopping with.
- `Favorites\ToggleFavorite` — writes or deletes the `favorites` row and
  records the matching listing event.

### Decisions

- **`Shop\ShopController` is the base for every storefront controller.** It
  holds the visitor (`customer()`), the two counts the header renders on every
  page, and the order-ownership check. Without it each controller would repeat
  the header query, and a layout-wide view composer would mean editing
  `AppServiceProvider`, which this ticket does not own.
- **The header counts are guarded with `@isset` in the layout.** `/login` and
  `/auth/magic/{token}` render `layouts.shop` without running the identity
  middleware, so those pages have no cart to count.
- **Every listing page view records a `view` event, including a repeat visit.**
  The seller's counts are page views, not unique visitors.
- **A declined charge leaves the retry form on the order page**, next to the
  reason from `Payment::decline_reason->message()`. `/orders/{id}/pay` and the
  order page post to the same route, so a retry is another `FinalizeOrder`.
- **Sold listings keep their page** (`ListingAvailability::isOnStorefront`)
  and show "Sold" with no cart button; drafts and archived listings 404.
- **Search drops rather than escapes `%` and `_`.** See `ListingSearch`.
- **No new factories.** Tests build state through the actions, per the ticket.

### Deviations

- Added `tests/StorefrontTestCase.php` (extends `Tests\CommerceTestCase`). The
  identity cookie is what ties a visitor's requests together and the test
  client does not keep the one the middleware queues, so a flow that spans
  requests pins its visitor with `visitor()` / `arriveAs()`.
- Rewrote `resources/views/shop/home.blade.php`, `shop/account.blade.php`,
  `layouts/shop.blade.php`, `Shop/AccountController.php` and
  `Shop/StorefrontController.php` from FEAT-001/FEAT-002. All are storefront
  files this ticket owns; the FEAT-002 account assertions were kept.
- No FEAT-002 or FEAT-003 file was edited — not even an additive relation.
  Views reach for money through `@use('App\Domain\Money\Money')`.

### Tests

75 tests for this ticket (17 pure unit, 5 action, 53 HTTP). Full suite green
at commit time, including FEAT-004's in-flight files.

### Verified in the browser

`make assets`, then a curl walk of the real app at `localhost:8000`: add to
cart -> `/checkout` -> pending order -> "Check your email" -> the debug magic
link -> `/orders/4/pay` -> `4000000000000002` -> "Your card was declined." ->
`4242424242424242` -> "Paid · $99.00", with `/account` showing the address
that was verified on the way through.

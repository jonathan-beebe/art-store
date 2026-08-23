---
id: RFCTR-007
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-007: Placing, paying and rolling up an order on Order

## Problem
`Orders::PlaceOrder`, `FinalizeOrder`, `MarkAwaitingPayment`, `RollUpOrderStatus` under `src/app/actions/orders` and `Domain::Orders::{OrderStatus,OrderPayment,OrderStock,PaymentAttempt,Purchaser,ShippingAddress}`, `Domain::Shop::{CheckoutForm,CheckoutPurchaser}`, `Domain::Listings::{ListingStock,StockChange}`, `Domain::Payments::*` carry the order lifecycle. `Shop::CheckoutsController` validates the shipping address by hand through `CheckoutForm#complete?` and assembles a `Purchaser`; `Shop::OrderPaymentsController` and `DeliveryConfirmationsController` call `OrderStatus.can_transition?` inline.

## Goal
`Order` owns its lifecycle and validates what checkout collects.

## Outcome
An order is placed from a cart, paid with a card number, moved to awaiting payment, and rolled up from its fulfillments through methods on `Order` (and stock moves through `Listing`); the shipping fields and email are validated on `Order`; the fake card decision is a plain model; the `app/actions/orders` tree and the listed domain files are gone; every checkout, payment and order test plus the lifecycle tests pass unchanged.

## Why it matters
`Order` today is associations and a `total` reader; the transition table, the stock rules and the payment rules that define an order are in three other directories.

## Discovery notes
Keep the `INCOMPLETE` flash text and `:unprocessable_content` responses. The transition table can stay a constant on `Order` with a small guard; `enum` gives `paid?`/`awaiting_payment` for free. `Purchaser` collapses into `Customer` (`email_verified?`). Seeds write history at fixed times through these methods; an optional `at:`/`now:` keyword is enough.

## Related work
- RFCTR-006
- RFCTR-008
- RFCTR-011

## Working

`Order` now carries the lifecycle. `Order.place(cart:, customer:, email:,
shipping:, email_verified:, at:)` snapshots the items at the price they were
bought at, splits the order into one fulfillment per seller with the platform
fee taken out, takes the stock through `Listing#take_stock!` and empties the
cart; it raises on an empty cart. `Order#pay!(card_number, at:)` reads a
`FakeCard`, moves the order through `Order::TRANSITIONS`, writes one `payments`
row per attempt, hands the stock back on a decline and claims it again on a
retry, and on approval holds each seller's net in escrow and sends the "Item
sold" notification. `mark_awaiting_payment!`, `roll_up_status!`,
`transition_to!`, `next_statuses`, `awaits_card?`, `unpaid?` and
`payable_by?` complete the surface.

`Listing#take_stock!` / `#restore_stock!` replace `Domain::Listings::ListingStock`
and `StockChange`, keeping the same `ArgumentError` messages. `FakeCard` is a
plain model reading one number; `Payment` holds the `status` and
`decline_reason` enums and the decline messages the storefront renders.

Deleted: `app/actions/orders/`, `app/domain/orders/{order_status,order_payment,
order_stock,payment_attempt}.rb`, `app/domain/payments/`, `app/domain/listings/`
and their tests, whose behaviour moved into `test/models/{order,order_lifecycle,
listing,fake_card,payment}_test.rb`.

Left alone: `Domain::Orders::FulfillmentStatus` (RFCTR-008), the escrow value
objects (RFCTR-009) and `Notifications::Notify` (RFCTR-010). `Fulfillments::
MarkShipped` and `ConfirmDelivered` lost their `roll_up_order_status:`
constructor argument and call `order.roll_up_status!` directly.

Checkout now validates on `Order`. The model includes `EmailAddress`,
normalizes the seven shipping fields (strip, blank to nil) and validates the
email shape plus the six required shipping fields; `Order.place` returns the
order unsaved when it is invalid, so nothing is written and the cart is left
alone. `Shop::CheckoutsController#create` reads `@order.persisted?` for the
`INCOMPLETE` alert and `@order.awaiting_payment?` for whether to charge, and
picks the buyer's address itself: the account's when the visitor is signed in,
the typed one otherwise. The checkout form reads its values off `@order`; the
field names, the HTML and the flash text are unchanged.

`Domain::Shop::{CheckoutForm,CheckoutPurchaser}` and
`Domain::Orders::{Purchaser,ShippingAddress}` are deleted. The one behaviour
that changed: an email typed in mixed case is rendered back downcased on the
rejected form, since the order normalizes it on assignment.

---
id: RFCTR-007
type: refactor
status: open
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

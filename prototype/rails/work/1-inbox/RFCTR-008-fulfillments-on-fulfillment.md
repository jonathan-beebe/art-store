---
id: RFCTR-008
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-008: Shipping and delivery on Fulfillment

## Problem
`Fulfillments::MarkShipped` and `Fulfillments::ConfirmDelivered` under `src/app/actions/fulfillments` hold the shipped/delivered transitions, the escrow release and the "Order shipped" notification; `Domain::Orders::FulfillmentStatus` holds the transition table; `Domain::Orders::ShipmentDetails` validates carrier and tracking number for `Seller::ShipmentsController`.

## Goal
`fulfillment.ship!(carrier:, tracking_number:)` and `fulfillment.deliver!` are the whole story.

## Outcome
Shipping and delivery are methods on `Fulfillment` that validate their inputs, write the ledger release and notify the customer; the order roll-up follows from the fulfillment change; the actions and the two domain modules are gone; the seller shipments and customer delivery-confirmation tests pass unchanged, including the `"A shipment needs a carrier and a tracking number."` refusal.

## Why it matters
`Fulfillment` is the seller's view of an order; its transitions belong where a reader of the model looks first.

## Discovery notes
Validation `on: :ship` context or a guard that raises the existing `TransitionError` both fit. Escrow movement API is RFCTR-009's concern; coordinate the interface.

## Related work
- RFCTR-007
- RFCTR-009
- RFCTR-011

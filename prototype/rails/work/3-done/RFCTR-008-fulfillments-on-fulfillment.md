---
id: RFCTR-008
type: refactor
status: resolved
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

## Working

`Fulfillment` now carries the enum, the transition table, `ship!`,
`deliver!`, `can_transition_to?`, `departed?` and the `subtotal`/`fee`/`net`
money readers. `ship!` normalizes the carrier and tracking number, validates
them in the `:ship` context, writes the row, rolls the order up and notifies
the customer; `deliver!` validates in the `:deliver` context, writes
`delivered_at`, releases the escrow and rolls the order up. Both refusals are
validation errors, so a blank field and an illegal move reach the seller's
form the same way and in the same order as before: missing details first, the
transition message second.

`Seller::ShipmentsController#create` calls `ship!` and renders `refused` with
the first error message; it lost the `MISSING_DETAILS` constant and the
`refuse` helper. `Shop::DeliveryConfirmationsController` asks
`can_transition_to?(:delivered)` before calling `deliver!`.
`Seller::OrdersController#index` groups over `Fulfillment.statuses.keys`, and
the seller order page asks `@fulfillment.can_transition_to?(:shipped)`.
`Order#roll_up_status!` reads its fulfillment records rather than plucked
strings, so `Order#departed?` is gone in favour of `Fulfillment#departed?`.

Deleted: `app/actions/fulfillments/`, `app/domain/orders/`, and their tests.
The behaviour they covered moved to `test/models/fulfillment_test.rb`. The
seeds, the integration helpers, the lifecycle, payout, controller and smoke
tests call `ship!`/`deliver!` and read enum predicates. The
`create_fulfillments` migration defaults `status` to the literal
`"awaiting_shipment"` now that the constant is gone.

Left alone: the escrow ledger write still builds a `Domain::Escrow::LedgerMovement`
(RFCTR-009's interface) and the shipping notification still goes through
`Notifications::Notify` (RFCTR-011).

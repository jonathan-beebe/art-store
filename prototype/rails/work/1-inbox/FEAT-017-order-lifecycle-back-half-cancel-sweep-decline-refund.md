---
id: FEAT-017
type: feature
status: open
created: 2026-08-23
---

# FEAT-017: Order lifecycle back half — cancel, stale sweep, seller decline, admin refund

## Problem
`Order` has a `cancelled` status nothing reaches: no cancel route, no stale-order sweep (guest orders hold stock indefinitely), no seller decline, no admin cancel or refund, no reversal ledger entry type, and no `refunds` table. `docs/alignment.md` §4 fixes the shared state machines, the `refunds` row, the `refunded` ledger entry and its three timings, the sweep, the sad paths, and the surfaces.

## Goal
The whole money-and-stock lifecycle of an order — happy and sad — is reachable, refused where it must be, and visible on every site.

## Outcome
The order and fulfillment state machines in §4.1 are enforced by the enums and model rules; the customer can cancel an unpaid order; `make sweep` (a rake task) cancels stale `pending_verification` orders older than `STALE_ORDER_HOURS`; a seller can decline an `awaiting_shipment` fulfillment with a reason and stock returns; an admin can cancel an unpaid order and refund any fulfillment with a reason; every refund writes a `refunds` row and a `refunded` ledger entry so the balance fold matches §4.2 in all three timings; every sad path in §4.3 has a test; the surfaces in §4.4 exist on the storefront, seller portal, and admin site; the counterpart is notified; `docs/orders.md` and `docs/escrow.md` show the new diagrams.

## Why it matters
Refunds and declines decide whether sellers trust the platform and whether the ledger reconciles; the prototypes compete on exactly this.

## Discovery notes
Rich models: `Order#cancel!`, `Fulfillment#decline!(reason:, by:)`, `Fulfillment#refund!(reason:, by:)`, `Order.sweep_stale(before:)` inside `transaction` with `lock!`, refusals as a domain error the controllers map to a refusal page; the roll-up of order status from live fulfillments as one method. The admin order/fulfillment detail pages come with FEAT-019; sequence the two so the actions have a page to live on.

## Related work
- docs/alignment.md §4
- FEAT-019
- FEAT-003 (commerce core)

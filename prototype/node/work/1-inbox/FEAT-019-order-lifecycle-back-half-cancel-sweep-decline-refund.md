---
id: FEAT-019
type: feature
status: open
created: 2026-08-23
---

# FEAT-019: Order lifecycle back half — cancel, stale sweep, seller decline, admin refund

## Problem
`ORDER_STATUSES` includes `cancelled` and the customer can cancel an unpaid order, but nothing sweeps stale `pending_verification` orders (they hold stock forever), a seller cannot decline a fulfillment, an admin cannot cancel or refund, `LEDGER_ENTRY_TYPES` has no reversal, and there is no `refunds` table. `docs/alignment.md` §4 fixes the shared state machines, the `refunds` row, the `refunded` ledger entry and its three timings, the sweep, and the sad-path list.

## Goal
The whole money-and-stock lifecycle of an order — happy and sad — is reachable, refused where it must be, and visible on every site.

## Outcome
The order and fulfillment state machines in §4.1 are enforced (DB CHECK constraints for the new statuses, transitions in the core); `make sweep` cancels stale `pending_verification` orders older than `STALE_ORDER_HOURS`; a seller can decline an `awaiting_shipment` fulfillment with a reason and stock returns; an admin can cancel an unpaid order and refund any fulfillment with a reason; every refund writes a `refunds` row and a `refunded` ledger entry so the balance fold matches §4.2 in all three timings; every sad path in §4.3 has a test; the surfaces in §4.4 exist on the storefront, seller portal, and admin site; the counterpart is notified; `docs/orders.md` and `docs/escrow.md` show the new diagrams.

## Why it matters
Refunds and declines are the half of commerce that decides whether sellers trust the platform and whether the ledger can ever be reconciled; the prototypes compete on exactly this.

## Discovery notes
Node already has `planOrderPlacement`, `stockChangeBetween`, and the customer cancel — the decline and refund are plans of the same shape (pure decision → applied in one `BEGIN IMMEDIATE` transaction). The roll-up of order status from live fulfillments is one pure function. The sweep is a CLI like `run-payouts.ts` with `main(argv, env)`. Admin order/fulfillment detail pages are BUG-007's territory; land that first.

## Related work
- docs/alignment.md §4
- BUG-007
- RFCTR-001 (escrow core)

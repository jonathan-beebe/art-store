---
id: FEAT-019
type: feature
status: open
created: 2026-08-23
---

# FEAT-019: Admin directory — sellers, customers, listings, orders, fulfillments

## Problem
The admin site (`FEAT-009`) has a sellers list with a listing count, a verified-only customers list (anonymous rows 404), a customer detail with an order count, and messaging. It has no seller detail with listings/fulfillments/payouts/balance, no customer standing filter or merge history, no favorites/cart on the customer detail, no cross-seller listings list or detail, no orders or fulfillments lists or details. `docs/alignment.md` §5 lists the pages and filters the Node admin site answers and the other two adopt.

## Goal
An operator can find and read any seller, customer (anonymous included), listing, order, or fulfillment on the platform from the admin site.

## Outcome
`/admin/sellers/:id` shows listings, fulfillments, payouts, and the folded balance; `/admin/customers?standing=all|verified|anonymous|blocked` and the detail with orders, favorites, cart, block and merge history; `/admin/listings?status=&seller=&removed=` and `/admin/listings/:id`; `/admin/orders?status=&customer=` and `/admin/orders/:id` with items, payments, fulfillments, refunds; `/admin/fulfillments?status=&seller=` and `/admin/fulfillments/:id`; every filter optional with empty meaning all; balances folded once per page; every page 404s on an unknown id; tests per page and filter, with a `count_queries` assertion on each list; `docs/admin.md` written.

## Why it matters
The alignment brief says the three prototypes support the same CX; the admin site is where Rails is furthest behind Node.

## Discovery notes
Node's `docs/admin.md` (on `main`) is the reference for pages, filters, and the folded-balance rule. Rails idiom: resourceful controllers under `Admin::`, scopes for filters, `includes` for the counterpart rows, partials for the tables, the existing admin `before_action` guard.

## Related work
- docs/alignment.md §5
- FEAT-009

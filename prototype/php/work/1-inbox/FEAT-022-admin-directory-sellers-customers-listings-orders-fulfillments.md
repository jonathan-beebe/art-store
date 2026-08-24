---
id: FEAT-022
type: feature
status: open
created: 2026-08-23
---

# FEAT-022: Admin directory — sellers, customers, listings, orders, fulfillments

## Problem
The admin site (`FEAT-010`, `FEAT-014`) has a sellers list with counts only, a customers list, a customer detail with orders, block/lift, and messaging. It has no seller detail with listings/fulfillments/payouts/balance, no customer standing filter or merge history, no favorites/cart on the customer detail, no cross-seller listings list or detail, no orders or fulfillments lists or details. `docs/alignment.md` §5 lists the pages and filters the Node admin site answers and the other two adopt.

## Goal
An operator can find and read any seller, customer, listing, order, or fulfillment on the platform from the admin site.

## Outcome
`/admin/sellers/{seller}` shows listings, fulfillments, payouts, and the folded balance; `/admin/customers?standing=all|verified|anonymous|blocked` and the detail with orders, favorites, cart, block and merge history; `/admin/listings?status=&seller=&removed=` and `/admin/listings/{listing}`; `/admin/orders?status=&customer=` and `/admin/orders/{order}` with items, payments, fulfillments, refunds; `/admin/fulfillments?status=&seller=` and `/admin/fulfillments/{fulfillment}`; every filter optional with empty meaning all; balances folded once per page; every page 404s on an unknown id; tests per page and filter; `docs/admin.md` written.

## Why it matters
The alignment brief says the three prototypes support the same CX; the admin site is where PHP and Rails are furthest behind Node.

## Discovery notes
Node's `docs/admin.md` (on `main`) is the reference for pages, filters, and the folded-balance rule. Laravel idiom: resource controllers under `Admin\`, query scopes for filters, Blade components for the tables, policies already gate the admin guard.

## Related work
- docs/alignment.md §5
- FEAT-010, FEAT-014

---
id: BUG-007
type: bug
status: open
created: 2026-08-23
---

# BUG-007: Admin order and fulfillment links lead to 404

## Problem
The admin orders and fulfillments lists (`src/app/sites/admin/`) link each row to `/admin/orders/:id` and `/admin/fulfillments/:id`, but no route answers those paths (`docs/admin.md` lists only the list routes); every link on `/admin/orders` and `/admin/fulfillments` is dead.

## Goal
An operator can open any order or fulfillment from the admin lists and read everything about it.

## Outcome
`GET /admin/orders/:id` shows the order's customer, items, payments, fulfillments, and refunds; `GET /admin/fulfillments/:id` shows the fulfillment's order, seller, items, ledger entries, and status; both pages 404 on an unknown id; the list links resolve; both pages are covered by route tests and appear in `docs/admin.md`.

## Why it matters
The admin site is the reference the other two prototypes are being aligned to; dead links in the reference get copied.

## Discovery notes
FEAT-019 adds the cancel/refund actions to these same pages; land this ticket first so the pages exist.

## Related work
- FEAT-006 (admin site)
- FEAT-019

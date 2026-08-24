---
id: BUG-007
type: bug
status: resolved
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

## Working

### Correction to the Problem statement

The admin orders and fulfillments list views carried no anchors at all before
this ticket — rows were plain `<tr data-order="<%= order.id %>">` /
`<tr data-fulfillment="<%= fulfillment.id %>">` with no `<a>` inside them. The
links were absent, not dead. The Outcome stands unchanged: the detail routes
now exist and the list rows link to them.

### What was built

- `GET /admin/orders/:id` (`app/sites/admin/routes/orders.ts`) reads
  `orderDetail` (`app/sites/admin/queries/order-detail.ts`) and renders
  `order.ejs`: customer (linked to `/admin/customers/:id`), items (linked to
  their listing, with `lineTotalCents` priced through the same `cartLineTotal`
  the shop order page uses), payments (status, amount, card, decline reason),
  and fulfillments (one row per seller, linked to `/admin/fulfillments/:id`).
- `GET /admin/fulfillments/:id` (`app/sites/admin/routes/fulfillments.ts`)
  reads `fulfillmentDetail` (`app/sites/admin/queries/fulfillment-detail.ts`)
  and renders `fulfillment.ejs`: order (linked back), seller (linked to
  `/admin/sellers/:id`), status, carrier/tracking when shipped, items, and
  every `ledger_entries` row this fulfillment carries.
- Both queries are unscoped admin reads, written fresh rather than by
  threading an optional scope parameter through `findCustomerOrder` /
  `ownedFulfillment` — the contract's instruction, taken deliberately even
  though it duplicates some of `findCustomerOrder`'s SQL shape.
- `views/orders.ejs` and `views/fulfillments.ejs` rows now link their id
  (and, on the fulfillments list, the order id) to the new detail pages, in
  the same `<a class="underline">` style as `customers.ejs` / `sellers.ejs`.
- `docs/admin.md`'s route table lists both detail routes and their queries.

### Left for FEAT-019

No refunds section, no Cancel or Refund action/form, no `refunds` table, no
`declined`/`refunded` statuses, no ledger `refunded` type — none of that
exists yet. `order.ejs` ends after the Fulfillments section and
`fulfillment.ejs` ends after the Ledger section, so FEAT-019 adds a Refunds
section and the action forms after what is already there without restructuring
either template.

### Tests

Route tests added to `orders.test.ts` and `fulfillments.test.ts`: the detail
page renders for a real id and shows every field the contract's §5 row lists;
an id of the right shape that names nobody is 404; a wrong-prefix id
(`ful_…` at `/admin/orders/:id`, `ord_…` at `/admin/fulfillments/:id`)
answers the identical 404 body as the miss case, proving both reach
`isRefusedRouteParams` / `callNotFound` the same way; a signed-out visitor is
redirected (302) by the existing `requireAdmin` hook on `adminConsoleRoutes`,
unchanged. The two list-page tests each gained an assertion that the row's id
(and, for fulfillments, the order id) links to the right detail path.

`make check`: 1623 → 1631 tests, all passing; coverage 99.53/97.32/99.51 →
99.53/97.34/99.51 lines/branches/functions. `make smoke` (8/8) and
`make routes` both confirm `/admin/orders/:id` and `/admin/fulfillments/:id`
are registered. Manually verified live against seeded data: signed in through
the flash-delivered magic link, loaded both new pages, and confirmed the
cross-links (order ↔ fulfillment ↔ seller ↔ customer ↔ listing) resolve.

### Deviations from the contract

None. `order.ejs` renders customer, items, payments, fulfillments as §5
requires (refunds excluded per the scope fence); `fulfillment.ejs` renders
order, seller, items, ledger entries, and status as the Outcome requires.

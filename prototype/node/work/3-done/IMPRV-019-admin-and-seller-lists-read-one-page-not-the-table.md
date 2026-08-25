---
id: IMPRV-019
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-019: Admin and seller lists read one page, not the table

## Problem
Outside the storefront (the only paginated surface — `sites/shop/queries/find-storefront-listings.ts:100-101`), every list reads its entire table:

- `orderRows` (`sites/admin/queries/order-rows.ts:29-53`): all orders (`selectAll`, eight shipping columns included) plus all items and all fulfillments, no LIMIT. Group-building at `:90` uses the spread-append pattern (rebuilt array per row).
- `outboxRows` (`sites/admin/queries/outbox-rows.ts:9`): every outbox message ever, full `body` text included, delivered or not.
- `customerRows` (`sites/admin/queries/customer-rows.ts:25-50`): every customer plus three whole-table GROUP BYs, then the `standing` filter applied in JS after fetching everything.
- `listingRows` (`sites/admin/queries/listing-rows.ts:35-60`): the `removed` filter applied in JS at `:57-59` after fetching all listings.
- `fulfillmentsForSeller` (`sites/seller/queries/fulfillments.ts:7-20`): every fulfillment ever for the seller's orders/earnings pages; spread-append grouping at `:102`.
- `sites/seller/queries/notifications.ts:8-21` and `payouts.ts`: unbounded per-seller reads.
- `findCustomerOrders` (`sites/shop/queries/find-customer-orders.ts:41-44`): `items.filter(...)` inside `orders.map(...)` — O(orders × items) where the repo's own `itemTitlesByOrder` Map-rollup idiom fits.

Index gap under the same pages: orders carries only `(customer_id, placed_at)` (`migrations/20260823000003-create-orders.ts:38`), so status-filtered admin views and `sweepStaleOrders` (`app/actions/orders/sweep-stale-orders.ts:45-52`, `WHERE status = ? AND placed_at < ?`) full-scan.

## Goal
List-page cost scales with the page shown, not with the table behind it.

## Outcome
Admin and seller list pages read a bounded number of rows per render, with their filters applied in SQL; status-filtered order reads are served by an index; row grouping is linear; visible page content and filter behavior are unchanged.

## Why it matters
These pages are fine with seed data and fall over first as real data accumulates — cost is proportional to table size on every render. The storefront already demonstrates the pattern this ticket extends, so the fix is convergence, not invention.

## Discovery notes
- The existing `orderBy (createdAt, id)` pairs are keyset-ready; keyset pagination avoids deep-OFFSET cost. Offset pagination matching the storefront is also a defensible first cut.
- Narrow list projections (id, status, totals, dates) — `selectAll` drags shipping columns and message bodies the rows never show.
- `standing` / `removed` filters express as `email is null` / `is not null` / `EXISTS(block)` predicates in WHERE.
- One migration adds `orders(status, placed_at)`.

## Related work
- IMPRV-018 (the money folds on the same pages)
- FEAT-005 (storefront pagination, the in-repo precedent)

## Working

2026-08-25 — plan of record:

- Page math: move `core/shop/listing-page.ts` to `core/paging/list-page.ts`
  (`listingPage` → `listPage`, `ListingPage` → `ListPage`), storefront callers
  updated. Shared pager partial `views/partials/pager.ejs` (basePath, query,
  param, page) for admin/seller lists; the storefront keeps its own markup.
- Offset pagination matching the storefront; 25 rows per admin/seller page.
- Admin: orders, customers, listings, outbox get page params, SQL filters
  (`standing`, `removed`), narrowed projections (orders drop shipping columns,
  outbox drops `body`), count queries for the pager. Per-page rollups scope to
  the page's ids.
- Seller orders: paginate the flat fulfillment list, group within the page;
  heading counts come from one grouped COUNT so totals stay true.
- Seller earnings: `sales_page` and `movements_page` page the sold-goods and
  movements tables independently. Payouts stay unbounded — one row per weekly
  period, bounded in practice.
- Seller notifications: page param + pager.
- `find-customer-orders.ts` titles roll up through a Map; the two
  spread-append groupings (`fulfillments.ts` itemTitlesByOrder, `order-rows.ts`
  fulfillment statuses) become push-appends.
- Index: `orders(status, placed_at)` added to the existing orders migration;
  `make fresh` rebuilds.

2026-08-25 — resolved. What landed against the plan:

- Page math lives in `core/paging/list-page.ts` (`listPage`/`ListPage`);
  storefront callers updated; old `core/shop/listing-page.ts` deleted.
  Shared `views/partials/pager.ejs` renders nothing at one page, so
  single-page screens are pixel-identical.
- Admin orders/customers/listings/outbox paginated at 25 rows with count
  queries sharing one predicate builder per surface
  (`ExpressionBuilder → Expression<SqlBool>[]` pattern from the storefront).
  `standing` and `removed` filters run in WHERE; an equivalence test pins the
  standing SQL to `isVerifiedCustomer`/`isAnonymousCustomer` (a blocked
  verified customer still counts as verified). Order rows select six columns;
  outbox list rows drop `body`/`url` (`OutboxListRow`); customer rollups scope
  to the page's ids.
- Seller orders paginate the flat fulfillment list and group within the page;
  heading counts come from one GROUP BY so totals stay true. A group whose
  rows sit on another page says "None on this page"; a truly empty one keeps
  "Nothing here."
- Seller earnings pages sold goods and movements independently
  (`sales_page`/`movements_page`), each pager preserving the other's position.
- Deliberate cuts: `payoutsForSeller` stays unbounded (one row per weekly
  period); `recentNotificationsForSeller` already limits; the storefront
  keeps its own inline pager markup; the shop account order list keeps its
  per-customer read (Map rollup only, per the ticket).
- Validation: reviewer found no blocking defects; three minor findings
  (page-2 empty-state copy, paging param type convergence, URLSearchParams in
  customers route) fixed. `make check` green; coverage 99.44/95.82/99.44.

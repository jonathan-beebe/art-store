---
id: FEAT-006
type: feature
status: open
created: 2026-08-22
---

# FEAT-006: Admin site — users, orders, accounting, site stats, moderation

## Problem
`/admin` is a placeholder. The brief adds a platform-operator role the earlier spikes lacked: site owners sign in and administer all users, see every listing / order / fulfillment / earning, app-wide stats and full accounting, every customer known or anonymous, page-view stats, and tools to review sellers (remove a listing temporarily or permanently) and customers (block).

## Goal
An admin signs in and can answer "what is happening on the platform" and act on a seller or customer from one place.

## Outcome
- `/admin` dashboard: counts of sellers, customers (verified vs anonymous), listings by status, orders by status, fulfillments by status, money held / released / paid out, platform fees earned, page views today and this week.
- Sellers list and detail: listings, fulfillments, ledger balance, payouts, active removals; customers list (verified and anonymous) and detail: orders, favorites, cart, merges, block status.
- Listings, orders, fulfillments, payouts, ledger entries: filterable tables with links into the detail pages.
- Accounting page: per-seller and platform totals that reconcile with the ledger.
- Site stats page: page views by day and by path pattern, listing events by type, all from the rollup tables.
- Moderation: remove a listing (temporary or permanent, with reason) and lift a temporary removal; block a customer (reason) and lift the block. The storefront and portal reflect each within the same request cycle.
- Payouts: "Run weekly payout" with an as-of date, showing what it created.
- Every page is behind `requireAdmin`; seller and customer cookies do not open it.
- Page-view rollup hook records successful HTML GETs by site, route pattern, and day.
- Integration tests cover sign-in gating, moderation effects on the storefront (listing disappears, blocked customer refused at checkout), and the payout run.

## Why it matters
Payouts, refunds, disputes, and suspension are platform actions; the retro named the missing admin role as the top gap of both earlier spikes.

## Discovery notes
Same theme as the seller portal. Reads go through Kysely queries in `app/sites/admin/queries/` (or actions where they change rows); aggregates that need a rule (status tallies, balance folds, fee totals) reuse the pure functions under `app/core/`.
- `listing_removals`, `customer_blocks`, `page_view_counts` tables and the predicates exist from FEAT-003; this ticket adds the write actions (`removeListing`, `liftListingRemoval`, `blockCustomer`, `liftCustomerBlock`) and the hook.
- Fastify exposes `request.routeOptions.url` for the route pattern; record in `onResponse` for `text/html` 200 GETs only, upsert by (site, pattern, day).
- Touch only `app/sites/admin/**`, `app/actions/moderation/**`, `app/actions/analytics/**`, and one registration line in `app/app.ts`. FEAT-004 / FEAT-005 / FEAT-008 run in parallel — commit with an explicit pathspec.

## Related work
- `__local__/retro.md` item 6 (a platform/admin role) and item 8 (roll up views).

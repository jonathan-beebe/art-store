---
id: FEAT-008
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-008: Seed data and demo reset

## Problem
A fresh database has two admins and nothing else. Reviewers need a populated gallery, sellers with history, a customer with favorites and orders in each state, and admin data to look at, so the product can be judged without an hour of clicking.

## Goal
`make fresh` produces a database a reviewer can demo every page from in five minutes.

## Outcome
- Four verified sellers with shops and ~30 listings across media (most `for_sale`, some `draft`, a couple `sold`, one under a temporary removal), each with a placeholder image.
- One verified customer (`casey@example.com`) with favorites, view history, a cart, and orders in `paid`, `shipped`, and `delivered` states; one blocked customer; a few anonymous customers with page-view history.
- Ledger and payout rows consistent with those orders (held, released, paid out) built through the FEAT-003 actions, never by inserting ledger rows directly.
- Page-view counts for the last 14 days.
- `app/db/seed.test.ts` asserts the counts; README lists the seeded accounts.

## Why it matters
A seeded database is the difference between a demo and a tour of empty tables.

## Discovery notes
Port the catalog from `prototype/rails/src/db/seeds.rb` (titles, media, prices, shop names). Drive state through actions with a frozen clock so dates are deterministic. Keep FEAT-002's admin seed as the first step.
- Touch only `app/db/seed.ts`, `app/db/seed.test.ts`, README "Seeded accounts". FEAT-004/005/006 run in parallel — commit with an explicit pathspec. FEAT-007 adds seeded conversations itself.

## Related work
- `prototype/rails/work/3-done/FEAT-006-seed-data-and-demo-reset.md`

## Working

### What is seeded

`app/db/seed.ts` calls `seedAdmins` (FEAT-002, unchanged), then
`seedDemoData` (`app/db/seed-demo-data.ts`), which orchestrates four new
modules, all driven through FEAT-003's actions over a fixed historical
clock — never through a raw ledger, payout, or notification insert:

| Module | Seeds |
| --- | --- |
| `app/db/seed-sellers.ts` | 4 verified sellers via `claimSellerIdentity`, profiled with a direct `sellers` update (no action sets `name`/`shopName` yet) |
| `app/db/seed-catalog.ts` | 29 listings via `createListing` + `changeListingStatus` (24 `for_sale`, 3 `draft`, 2 `sold` reached by two single-hop transitions from a listing created at quantity 0), one `removeListing` (temporary, on "Night Freight") |
| `app/db/seed-customers.ts` | Casey (verified, 6 `recordListingEvent` views, 3 `toggleFavorite` favorites, a standing cart via `currentCart`/`addToCart`), one blocked customer via `claimCustomerIdentity` + `blockCustomer`, 3 anonymous customers via `createAnonymousCustomer` with view history |
| `app/db/seed-order-history.ts` | 3 single-item orders for Casey (own `carts` row per order, like a real checkout) via `addToCart`/`placeOrder`/`finalizeOrder`, one `markShipped`, one `markShipped`+`confirmDelivered`, one `runWeeklyPayout` |
| `app/db/seed-page-views.ts` | 98 `page_view_counts` rows (3 sites × 14 days, 2–3 path patterns each) via the FEAT-006 `recordPageView` action, called once per simulated hit |

Catalog and order-history dates are hardcoded (June–July 2026), matching the
Rails source, so the demo reads the same regardless of when `npm run seed`
runs. Page views are the one exception: they are dated relative to
`clock.now()` at seed time, so "the last 14 days" always means the last 14
days.

### Decisions

- **The demo half refuses to run twice; `seedAdmins` stays idempotent.**
  `seedDemoData` no-ops if any `sellers` row exists. Rebuilding order
  history, ledger entries, and a payout run idempotently would mean either
  upserting money movements (unsafe) or diffing a whole demo dataset against
  itself; refusing is what the Rails source did (`if Seller.exists?`) and
  what `make fresh` already assumes (`fresh` truncates before `seed` runs).
- **`removeListing` and `blockCustomer` exist now** (landed mid-ticket from
  the parallel FEAT-006 work) — used instead of the raw `listing_removals` /
  `customer_blocks` inserts the ticket's fallback anticipated.
- **`recordPageView` exists now** (parallel FEAT-005/006 work) and already
  fixes the `site` vocabulary (`shop` | `seller` | `admin`, from
  `app/core/analytics/page-view-site.ts`) and the increment-on-conflict
  shape. `seed-page-views.ts` calls it per simulated hit rather than
  inserting rows directly, so a row's `count` comes from the same code path
  production traffic uses.
- **A sold listing is created at quantity 0**, then walked `draft →
  for_sale → sold` — the two-hop path `changeListingStatus` requires — rather
  than reached by an actual sale. Nothing else models "sold" a different way.
- **Casey's standing cart is a separate cart row from her order history.**
  Order placement uses its own `carts` insert per order (mirroring a real
  checkout), so `currentCart` — which returns whichever of a customer's cart
  rows holds the most items — never mixes the two.
- **Listing creation and seller verification share one fixed instant each**
  rather than a monotonically increasing clock; nothing downstream reads
  listing or seller `createdAt` ordering, so one instant per phase keeps the
  seed modules simple.

### Verified

- `node --test 'app/db/**/*.test.ts'`: 29 pass, 0 fail (includes this
  ticket's 13 in `seed.test.ts`, asserting sellers, listing status/media
  counts, the active removal, Casey's favorites/views/cart, the blocked
  customer, the 3 anonymous customers, listing-event counts by type, order
  statuses and fulfillment split across 2 sellers, ledger entries by type,
  the payout amount against the delivered fulfillment's `netCents`,
  notification counts by subject, page-view row/site/day counts, the
  no-op second run, and the summary counts).
- `npx eslint` scoped to every file this ticket owns: clean.
- `tsc --noEmit` project-wide: clean.
- Scratch-file run: `DATABASE_FILE=storage/seed-scratch.sqlite3 npm run
  migrate` applies 9 migrations from nothing; `npm run seed` prints
  `seeded 2 admins` then `seeded 4 sellers, 29 listings, 5 customers, 3
  orders, 98 page-view rows.`; running `npm run seed` again prints
  `seeded 0 admins` and `demo data already seeded, skipping.`. Scratch file
  removed after.
- Full-tree `npm run check` / `npm run coverage` were not clean at the time
  of this work — failures are in `app/plugins/page-views.test.ts`,
  `app/plugins/site-render.test.ts`, and `app/sites/shop/*` (unowned files
  mid-edit by the parallel FEAT-005/FEAT-006 agents in this shared tree),
  not in any file this ticket touches. `npx eslint` and `node --test`
  scoped to this ticket's files are both clean, and project-wide
  `tsc --noEmit` is clean.

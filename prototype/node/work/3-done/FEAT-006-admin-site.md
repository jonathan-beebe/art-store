---
id: FEAT-006
type: feature
status: resolved
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

## Working

### The moderation action signatures (FEAT-007 and FEAT-008 call these)

```ts
// app/actions/moderation/
removeListing(context, { listingId, adminId, kind, reason }): Promise<ListingRemoval>
liftListingRemoval(context, { listingId }): Promise<ListingRemoval>
blockCustomer(context, { customerId, adminId, reason }): Promise<CustomerBlock>
liftCustomerBlock(context, { customerId }): Promise<CustomerBlock>

activeListingRemoval({ db }, listingId): Promise<ActiveListingRemoval | null>   // FEAT-003
activeCustomerBlock({ db }, customerId): Promise<ActiveCustomerBlock | null>    // new here
currentCustomerStanding({ db }, customerId): Promise<CustomerStanding>          // FEAT-003
```

`context` is `ActionContext` (`{ db, clock }`); every one runs in a transaction
through `runInTransaction`. `kind` is `RemovalKind` (`temporary` | `permanent`).
Both lifts key off the subject, not the removal or block row, so a caller that
knows the listing or the customer needs nothing else.

Each refusal is a `TransitionError` (`app/core/transition-error.ts`) the route
catches:

| Call | Refused when |
| --- | --- |
| `removeListing` | the listing already has an active removal |
| `liftListingRemoval` | the listing has no active removal, or it is `permanent` |
| `blockCustomer` | the customer is already blocked |
| `liftCustomerBlock` | the customer is not blocked |

### The page-view rollup

`addPageViewRollup(app)` (`app/plugins/page-views.ts`) is one line in
`buildApp`, next to `addFlash` and `addIdentity`. A root `onResponse` hook
reaches every site, and the site is read back off the route's own pattern by
`pageViewSite` (`app/core/analytics/page-view-site.ts`): `/seller` and `/admin`
claim their prefixes, everything else is `shop`. `isCountablePageView` (FEAT-003)
decides what counts; `recordPageView` (`app/actions/analytics/`) upserts
`page_view_counts` by the unique `(site, path_pattern, day)` index, so the first
hit of a day inserts and every later one increments in one statement.

A request that matched no route has no `request.routeOptions.url` and is counted
against nothing.

### Routes

Every one is inside `adminConsoleRoutes` (`app/sites/admin/console.ts`), which
adds `requireAdmin` once. `/admin/login`, `/admin/logout`, and `/admin/account`
stay outside it: a guard over the sign-in pages would send a signed-out admin
in a circle.

| Method | Path | Template | Reads / writes |
| --- | --- | --- | --- |
| GET | `/admin` | `home.ejs` | `platformTallies`, `platformMoney`, `pageViewTotals` |
| GET | `/admin/sellers` | `sellers.ejs` | `sellerRows` |
| GET | `/admin/sellers/:id` | `seller.ejs` | `sellerDetail` (404 when the id names nobody) |
| GET | `/admin/customers?standing=` | `customers.ejs` | `customerRows`; `standing` is `all` \| `verified` \| `anonymous` \| `blocked` |
| GET | `/admin/customers/:id` | `customer.ejs` | `customerDetail` (404) |
| GET | `/admin/listings?status=&seller=&removed=` | `listings.ejs` | `listingRows`; `removed` is `any` \| `removed` \| `visible` |
| GET | `/admin/listings/:id` | `listing.ejs` | `listingDetail` (404) |
| GET | `/admin/orders?status=&customer=` | `orders.ejs` | `orderRows` |
| GET | `/admin/fulfillments?status=&seller=` | `fulfillments.ejs` | `fulfillmentRows` |
| GET | `/admin/accounting` | `accounting.ejs` | `sellerAccounts`, `platformMoney` |
| GET | `/admin/payouts?seller=` | `payouts.ejs` | `payoutRows`, `sellerOptions` |
| POST | `/admin/payouts` | — | `runWeeklyPayout`, flash, redirect |
| GET | `/admin/ledger?seller=&type=` | `ledger.ejs` | `ledgerRows` (rows plus the folded totals for the filtered set) |
| GET | `/admin/stats` | `stats.ejs` | `pageViewsByDay`, `pageViewsByPattern`, `listingEventTallies` |
| POST | `/admin/listings/:id/removals` | — | `removeListing` (`kind`, `reason`, `redirect_to`) |
| POST | `/admin/listings/:id/removals/lift` | — | `liftListingRemoval` (`redirect_to`) |
| POST | `/admin/customers/:id/blocks` | — | `blockCustomer` (`reason`, `redirect_to`) |
| POST | `/admin/customers/:id/blocks/lift` | — | `liftCustomerBlock` (`redirect_to`) |

Reads live in `app/sites/admin/queries/`, one module per table a page shows:
`platform-tallies`, `platform-money`, `page-view-report`,
`listing-event-tallies`, `seller-rows`, `seller-detail`, `customer-rows`,
`customer-detail`, `listing-rows`, `listing-detail`, `order-rows`,
`fulfillment-rows`, `seller-accounts`, `payout-rows`, `ledger-rows`. Each takes
`Pick<ActionContext, 'db'>` and returns cents and ISO strings; the templates
format through `adminPage`.

### Decisions

- **`adminConsoleRoutes` is a plugin whose only job is the guard**, the same
  shape as `storefrontRoutes` and its identity hook. Putting `requireAdmin` on
  each route instead would let the next page forget it.
- **`adminPage(title, data)` hands every template `formatCents`,
  `formatMoment`, and `formatLabel`.** `addSiteRender` is shared by all three
  sites and not this ticket's to change, so the formatters ride in the render
  data. Queries stay in cents and the view does the formatting, so no route
  builds display strings.
- **Every moderation write goes through one `moderationRoute` factory**
  (`app/sites/admin/routes/moderation.ts`). The four differ only in their zod
  form, the action they call, and what they say afterwards; the shared shape is
  parse the id, resolve a local redirect, call the action, and turn a
  `TransitionError` into a flashed alert. The route never asks whether the move
  is allowed — that answer is the action's.
- **The refusals are `TransitionError`**, the class FEAT-003 already raises for
  a status that cannot move. A moderation refusal is the same kind of answer,
  so routes catch one class.
- **`redirect_to` on every moderation form** runs through `resolveLocalRedirect`
  (FEAT-002), so the listings table, the listing page, and the customer page can
  each act and stay where they were, and a tampered value cannot send an admin
  off-site.
- **The lifts key off the subject, not the removal or block row.** A page that
  knows the listing or the customer needs nothing else, and "which removal is
  the active one" stays a single answer in `activeRemoval` / `activeBlock`.
- **At most one active removal per listing, one active block per customer.**
  Raising a temporary removal to a permanent one is lift then remove, which
  leaves the seller one reason to read rather than two overlapping ones.
- **`activeBlock` was added to `app/core/moderation/customer-standing.ts`** and
  `customerStanding` now folds through it, mirroring `activeRemoval`. The admin
  site acts on the row, so it needs the id and the date the standing predicate
  does not carry; `activeCustomerBlock` is the action that reads it.
- **`tallyOver(keys, counted)` (`app/core/analytics/tally.ts`)** puts the states
  nobody has reached back on the page. A `group by` answers only for the states
  that have rows, and a dashboard that hides `payment_failed` because nothing
  failed yet is lying about the state machine.
- **"This week" for traffic is the seven days ending today**
  (`pageViewWeek`), not Monday-to-Sunday. A calendar week reads as almost
  nothing every Monday, and the number exists to be compared with the day
  before it.
- **Platform fees are earned when the order pays.** `platformMoney` sums
  `fulfillments.fee_cents` over fulfillments that have a `held` ledger entry, so
  an unpaid order's fee is not counted and the figure reconciles with escrow.
- **Balances are folded, never queried per seller.** The sellers list, the
  accounting page, and the ledger page each read `ledgerMovements` once and fold
  per seller with `ledgerBalance`, rather than calling `sellerBalance` N times.

### Deviations

- **`app/sites/admin/console.ts` and `app/sites/admin/page.ts` are new files the
  ticket did not name.** The guard needed somewhere that was not `index.ts`, and
  the formatters needed somewhere that was not every route.
- **`payouts.ts` parses `as_of` itself** rather than reusing `parseAsOf` from
  `app/cli/parse-as-of.ts`, which reads a `--as-of=` flag out of an argv array.
- **`app/test/smoke.test.ts` and `app/plugins/site-render.test.ts` now sign in
  as an admin before fetching `/admin`.** Both walked `/admin` as nobody, which
  the guard this ticket adds turns into a redirect. Both files also carry
  FEAT-004's seller-guard edits, so they are left uncommitted here.
- **The listing-removal writes moved out of `routes/listings.ts` into
  `routes/moderation.ts`**, which is what removed six duplicated helpers across
  the two files.

### Verified

- `npm test`: **967 tests, 967 pass, 0 fail** across the whole project with
  FEAT-004, FEAT-005, and FEAT-008 in the same tree. **160 of those are this
  ticket's**, in 37 sidecar files, plus one added to
  `app/core/moderation/customer-standing.test.ts`.
- `npm run coverage`: **99.47% lines, 96.01% branches, 98.76% functions**, exit
  0 against the unchanged 90 / 80 gate.
- `npm run typecheck` and `npm run lint` clean over the whole project
  (`complexity` 8, `max-depth` 3).
- Moderation reflects at once, asserted over HTTP, not only through the
  predicates: `POST /admin/listings/:id/removals` makes `/art/:slug` answer 404
  and the lift brings it back to 200; a blocked customer with a full cart is
  redirected from `/checkout` to `/cart` with the block's reason in the alert.
- Gating: `/admin` answers 302 to `/admin/login?redirect_to=%2Fadmin` for a
  visitor with no admin cookie, and for a seller cookie and a customer cookie
  alike.
- Curl walk on <http://localhost:4000> signed in as
  `jonathan-beebe@outlook.com` through the debug alert's magic link: all eleven
  pages 200, `/admin/sellers/99999` 404, the three filtered tables 200,
  `POST /admin/listings/1/removals` 302 then `/art/curl-walk-study` 404 then the
  lift then 200 again, `POST /admin/customers/1/blocks` 302 and the page reads
  `data-standing="blocked"` with the reason, a second block flashes
  "already blocked", the lift restores `unblocked`, and
  `POST /admin/payouts` with `as_of=2026-08-24` flashes
  "No seller had a released balance to pay for 2026-08-17 to 2026-08-23."
  against a development database with no released escrow. Every accounting row
  reconciles.
- `node --watch` in the container does not see every bind-mount write; touching
  `app/server.ts` forces the reload the curl walk needs.

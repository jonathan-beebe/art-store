---
id: IMPRV-025
type: improvement
status: open
created: 2026-09-02
---

# IMPRV-025: storefront requests stop crossing the slow-query threshold on the deploy

## Problem

On the Render deploy of prototype/php, a warm storefront browsing session
logs `query.exceed` lines of 80 to 87 ms for statements that do trivial
work:

- `select * from sessions where id = ?` and `update sessions set …`
  (`SESSION_DRIVER=database`; attributed to
  app/Http/Middleware/RollUpPageViews.php:32 because the session
  middleware's own frames are vendor code);
- `select * from cache where key in (?)` from
  app/Support/RateLimiting/RateLimitGate.php:55 (`CACHE_STORE=database`);
- the three-subquery badge count in
  app/View/Composers/ShopLayoutComposer.php:42 (cart quantity sum, unread
  notifications by morph, unread messages with an `exists` on
  conversations);
- the `listings` scan with `not exists (select * from listing_removals …)`
  in app/Support/Shop/MediumOptions.php:31.

Requests carrying one of these lines report `db.total_ms` of 86 to 89 ms
across 7 to 17 queries, with every other statement in the same request
under a millisecond, and a request `duration_ms` of 96 to 113 ms. Warm
requests without such a line total 3.3 to 3.8 ms of database time.

## Goal

A storefront page on the deploy answers with every statement under the
slow-query threshold and no per-request database work it does not need.

## Outcome

During a warm browsing session on the Render deploy (storefront index, a
listing page, a category page, the cart, with a signed-in customer), no
`query.exceed` line is written for the five statements above, and the
request line's `db.total_ms` for those pages stays under 20 ms; the
storefront issues no session write and no cache read against the commerce
database on a plain page view; the badge counts and the medium options
cost at most one statement each; the measurement is taken with RSRCH-001's
commands and recorded in the ticket before and after, on the deploy and
locally.

## Why it matters

The slow-query line exists to point at work worth removing. Every
storefront request today performs a session read, a session write, and a
rate-limit cache read on the commerce file, and the badge and
medium-option queries run on every page for a signed-in visitor. On the
deploy these are the statements that cross the threshold, and they are the
ones a shopper waits on.

## Discovery notes

- Measured on the deploy 2026-09-02 (Render service
  `srv-da6gb5710e5c73bknq9g`, free plan, `cpu_limit` 0.15, 512 MB, one
  instance, `app.boot` logged on every request). Across 100 `query.exceed`
  lines the median is 84 ms and the statement varies: session select,
  session update, cache select, `customers where id in (?)` from
  app/Support/ListPaneWindow.php:38, the listings scan. Seeder inserts at
  each boot show the same 80 to 120 ms.
- Read the constraint before choosing where to cut: a 0.15 CPU limit is
  15 ms of CPU per 100 ms scheduler period, and IMPRV-007's 16.6 ms of CPU
  per request means a request that boots Laravel crosses one period, with
  the wait landing inside whichever statement is running. The request
  line's wall time next to its CPU time (`getrusage`) would show whether a
  given line is a statement or a stall; adding that to the request line
  and the `query.exceed` line makes every later measurement here
  trustworthy.
- Query-side candidates, all measurable with the same commands:
  - sessions off the commerce file (the cookie driver, or a session store
    of its own the way analytics and logs have theirs); the session write
    on every page is the one per-request write left on the commerce
    connection;
  - the rate-limit cache off the commerce file (file or array store); the
    limiter reads it on gated routes only, so check which storefront GETs
    are gated;
  - the badge query's `unread_messages` arm: `EXPLAIN QUERY PLAN` on the
    `exists` subquery, an index on messages(conversation_id, read_at), or a
    denormalised unread counter;
  - MediumOptions' listing scan: an index on
    listing_removals(listing_id, lifted_at), or computing the options once
    per request rather than per composer;
  - ListPaneWindow's `customers where id in (?)` on the admin side, the
    same shape.
- Less CPU per request also counts as faster here: FrankenPHP worker mode
  ends the boot per request and is the one change that moves every
  statement at once; RSRCH-001's M5 gives the number.
- Record before-and-after for each change separately so the ticket says
  which one paid.

## Related work

- RSRCH-001 — the baseline and its commands
- IMPRV-007 — CPU per request, 24.7 to 16.6 ms
- IMPRV-017 — request lines carry query count and time
- IMPRV-022 — the slow-query line
- PR #61 — WAL on the app database
- FEAT-039 — analytics off the commerce file; the pattern for a per-request writer

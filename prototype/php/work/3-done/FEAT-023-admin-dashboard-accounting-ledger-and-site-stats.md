---
id: FEAT-023
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-023: Admin dashboard, accounting, ledger browser, and site stats

## Problem
`/admin` shows two links; there is no platform tally by status, no money summary, no per-seller reconciliation, no ledger browser, no page-view record at all, and `listing_events` writes one `view` row per GET with no collapse. `docs/alignment.md` §5 lists `/admin` (tallies for every status incl. zero rows, platform money), `/admin/accounting`, `/admin/ledger?seller=&type=`, `/admin/stats` (page views by day and route pattern, listing event tallies), and the roll-up rules.

## Goal
An operator can read the platform's state, money, and traffic from the admin site, from small tables.

## Outcome
`/admin` shows a tally for every listing/order/fulfillment status (zero rows still listed) and platform money (held, available, paid out, fees earned, fees refunded, refunded) and page views this week; `/admin/accounting` reconciles every seller; `/admin/ledger` browses entries with folded totals for the filtered set; `/admin/stats` shows page views by day (7-day window) and by route pattern plus listing event tallies; page views are rolled up at response time into `page_view_counts (site, path_pattern, day, count)` by one upsert; a listing `view` event is collapsed to one per (listing, customer, UTC hour); tests cover the tallies with empty states, the roll-up upsert, and the collapse; `docs/admin.md` gains the two diagrams.

## Why it matters
Retro item 8 and the product notes ask for traffic and accounting; without roll-ups the tables grow with traffic and the admin pages get slower every day.

## Discovery notes
Node's `pageViewRollup` `onResponse` hook and `isRecordedOncePerHour` are the reference; in Laravel a terminable middleware reading `$request->route()->uri()` for the pattern and an `upsert` on the unique `(site, path_pattern, day)` index. Database-side aggregation (RFCTR-006) is the existing idiom for the tallies.

## Related work
- docs/alignment.md §5
- RFCTR-006
- prototype/node FEAT-006

## Working

`make test`: **1699 tests, 4655 assertions**, 100.0 % of lines (from 1596 /
4438 at branch HEAD). Built in the order the ticket lays out: the roll-up
table and middleware, the `view` collapse, `/admin/stats`, the accounting
totals, `/admin/ledger`, `/admin` last.

### The page-view roll-up

`page_view_counts` (`pvc_`, unique on `(site, path_pattern, day)`) is written
by one terminable middleware, `App\Http\Middleware\RollUpPageViews`, appended
to the **global** stack in `bootstrap/app.php` beside `LogRequestStory` and
`SecurityHeaders` — the same reasoning both already carry: added at the root
it runs for every site, and it is terminable rather than answered inside
`handle()` because the write is not part of answering the request and should
cost the request nothing.

Three pure decisions live in `App\Domain\Analytics`, each with its own
sidecar test: `PageViewCountability::isCountable()` (GET, 2xx, `text/html`),
`PageViewSite::fromRoutePattern()` (`/seller` and `/admin` claim their
prefixes; everything else is `shop`), and `PageViewDay::of()` /
`PageViewWeek::endingOn()` (the UTC day, and the seven days ending on a given
one). The middleware does only I/O: read the route (`null` counts against
nothing), read the response, and hand the pure answers to
`App\Actions\Analytics\RecordPageView`, which is the one line with a query —
`PageViewCount::query()->upsert(...)` with `count = page_view_counts.count +
1` as the conflict target's raw update, so the first hit of a day inserts and
every later one increments in one statement.

`Route::uri()` carries no leading slash except the root route, which already
is one; the middleware normalises to a leading slash before anything reads
the pattern, so `/art/{listing}` is what is stored and what
`PageViewSite::fromRoutePattern` and every page reading `page_view_counts`
expect.

### The `view` collapse

`App\Domain\Listings\ListingViewCollapse` carries the two pure decisions:
`collapsesHourly(ListingEventType)` (true for `view` alone) and
`windowStart(DateTimeImmutable)` (floors to the UTC hour). `RecordListingEvent`
asks both before writing: a collapsed type checks for an existing row in the
window for the same `(listing, customer, type)` — `customer_id` compared with
`whereNull` when the visitor is anonymous, since Eloquent's `where('col',
null)` compiles to `col = ?` rather than `IS NULL` — and returns `null`
instead of a row when one is already there. `ListingController` reads that
`null` back and logs `refused` rather than `did`; `StoryEvent::ListingView
->refusalLevel()` already answered `debug` for exactly this (§2.3), so the
controller change is the whole of it.

### Tallies with nothing hidden

`Listing`, `Order`, and `Fulfillment` each gained a `countedByStatus` scope
(`select status, count(*) as tally group by status`) and a
`platformCountsByStatus()` static reading it back as `status value => count`.
Each folds through its own `*StatusTally::from()` (`App\Domain\Reports`)
against the enum's `cases()`, so a status with no rows still appears at zero —
matching the existing `ListingStatusTally` this ticket generalised the pattern
from, not a rewrite of it. `ListingEventTally` does the same for
`/admin/stats`'s event counts, reading `ListingEventType::label()` (added
here, mirroring every sibling status enum's `label()`).

### Platform money

`App\Domain\Escrow\LedgerBalance` gained a fourth field, `refunded` — the
positive amount refunded, folded in the same per-fulfillment loop that
already computes held/available/paid-out, so it costs the fold nothing extra
and no second query. `LedgerBalance::combine()` sums several balances
field-by-field; `LedgerBalances::total()` uses it to fold every seller's
already-computed balance into the platform's own, free of a second ledger
read — valid because a fulfillment belongs to exactly one seller, so grouping
by fulfillment across the whole ledger partitions into the same groups as
folding each seller alone and adding the results.

`App\Domain\Escrow\PlatformFees::from()` takes `{status, feeCents}` pairs and
splits them into earned (every `isLive()` fulfillment) and refunded (declined
or refunded) — the rule FEAT-020 wrote down for this ticket to build.
`Fulfillment::platformFees()` is the one query behind it.
`App\Domain\Escrow\PlatformMoney::of(balance, fees)` combines both into the
six figures `/admin` and `/admin/accounting`'s totals row show.

`/admin/accounting` reads `LedgerEntry::balancesBySeller()` once — the same
call `/admin/sellers` already made — and asks it for both the per-seller rows
(`$balances->of($seller->id)`, now carrying `refunded` too) and the platform
total (`$balances->total()`), so the page costs one ledger read whatever the
seller count; a query-count test holds it the way `SellerControllerTest`
already did.

### `/admin/ledger`

`LedgerEntry` gained an `ofSeller` scope beside its existing `ofType`, both
nullable-argument scopes adding no clause when the filter is absent — the
same idiom every other admin list filter already uses. The page's totals are
`LedgerBalance::from()` over the *filtered* rows rather than the platform's,
so a `type=held` filter folds a partial ledger into a partial balance; that is
what the ticket and Node's reference both call out on purpose.

### Deliberately left out

- Per-seller fees earned/refunded on `/admin/accounting`. FEAT-020's notes
  and §5 both put fees at the platform level only; the per-seller row is
  held / available / paid out / refunded.
- Zero-filling `/admin/stats`'s "by day" table for a day with no traffic —
  Node's own `pageViewsByDay` only lists days that saw a hit, and the ticket's
  zero-fill rule is written against listing/order/fulfillment *statuses*, not
  calendar days.
- A `label()` on `PageViewSite` — nothing reads one; the stats page shows the
  raw `shop` / `seller` / `admin` value the way Node's does.

### Deviations from §5, and why

- **`/admin/stats`'s listing-event tally is a platform total per type, not
  per listing.** Node's own doc paragraph says "per-listing view, favorite…"
  but its implementation (`listingEventTallies`) groups only by `eventType`,
  confirmed by its own test asserting `data-stat="event-view"` once per page.
  This prototype matches what Node actually ships. A per-listing breakdown
  already exists on `/admin/listings/{listing}` from FEAT-022
  (`loadEventCounts()`).

### For the Node and Rails lanes

- `refunded` is a fourth field on the balance object, not a separate query —
  worth matching if either lane computes it by re-querying `ledger_entries`.
- The listing-event tally on the stats page is a flat per-type total across
  the platform (see above), not grouped by listing.
- `RollUpPageViews` is global middleware with a `terminate()` method, not a
  `handle()`-time write — the write happens after the response is already
  sent, so it is free on the request it counts. Node's `onResponse` hook is
  the same idea in Fastify's terms.

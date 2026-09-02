---
id: FEAT-039
type: feature
status: open
created: 2026-09-01
---

# FEAT-039: an analytics store isolated from the app database

## Problem

Analytics writes share the main app database. Every countable response upserts
`page_view_counts` (app/Actions/Analytics/RecordPageView.php:22, via the
RollUpPageViews middleware) and every listing view writes `listing_events`
(app/Actions/Listings/RecordListingEvent.php), so the highest-frequency writes
in the system contend for the same sqlite file — and its write lock and commit
costs — as checkout, cart, and every storefront read. IMPRV-022's slow-query
log caught the page-view upsert at ~160ms against the 50ms threshold on a dev
database.

## Goal

Analytics load can never make a shopper's or seller's page slower — the same
isolation the logging subsystem already has.

## Outcome

Analytics data lives in its own store, separate from the app database file;
app-database write traffic contains no analytics writes; a page load completes
normally even when the analytics store is slow or unavailable; the surfaces
that read analytics today (admin site stats, admin dashboard traffic, the
seller listing detail's view counts, the listing-view hour-collapse dedup)
still render the same numbers; the suite stays green with parallel-safe test
isolation.

## Why it matters

The write-per-request pattern grows with traffic while commerce writes grow
with sales — coupling them means the cheapest, most-losable data (view counts)
can tax the most valuable requests (checkout). Logging already solved this
shape; analytics is the remaining per-request writer on the app connection.

## Discovery notes

- app/Logging/LogStore.php is the working precedent: its own sqlite file
  (env-configured like `LOG_DATABASE_FILE`, off in the suite), schema created
  imperatively, own pragmas (WAL, synchronous tuned, busy timeout), own
  retention/pruning. Analytics tolerates even weaker durability
  (`synchronous=OFF` is defensible — losing a view count is fine).
- Two tables are analytics today: `page_view_counts` (no FKs, read only by
  admin stats/dashboard — a clean move) and `listing_events` (carries
  `listing_id`, read by seller listing detail and admin stats, and queried for
  the hour-collapse dedup). Moving `listing_events` dissolves its FK and any
  cross-table join; readers would query the analytics connection and join in
  PHP if needed. Staging the work — `page_view_counts` first, `listing_events`
  second — de-risks it.
- A new env variable name is alignment-contract surface (docs/alignment.md
  names env vars); node and rails need the same subsystem for parity — likely
  their own follow-up tickets.
- Test isolation wants the LogStoreFixtures approach: each test builds the
  store against a temp file or `:memory:` explicitly; the suite-wide store
  stays off.
- Measure before/after with the existing slow-query log: after the move, no
  analytics statement should appear on the app connection at any threshold.

## Related work

- FEAT-033 (log store + admin viewer — the pattern to mirror)
- FEAT-019 (structured story logs)
- FEAT-023 (admin dashboard/stats — the readers)
- IMPRV-022 (slow-query logging — the instrument that surfaced this)
- PR #61 / branch php/sqlite-wal (WAL on the app database — the
  already-landed mitigation this builds past)

## Working

2026-09-02, branch `php/analytics-store`.

Re-validated: both writers still hit the app connection.
`RecordPageView` upserts `page_view_counts` from `RollUpPageViews::terminate`
on every countable response; `RecordListingEvent` writes `listing_events`
from the shop listing page, `AddToCart`, `ToggleFavorite`, and
`CustomerSeeder`. Readers: `Admin\StatsController`, `Admin\DashboardController`,
`Listing::loadEventCounts()` (seller and admin listing detail),
`Listing::eventCountsByDateSince()` (seller listing detail timeline),
`RecordListingEvent::alreadyRecorded()` (hour collapse), and
`MergeAnonymousCustomer` re-pointing `listing_events.customer_id` through
`CustomerOwnedTables::all()`.

Design:

- A second Laravel connection, `analytics`, in `config/database.php`:
  sqlite, `ANALYTICS_DATABASE_FILE` (default `storage/analytics.sqlite3`,
  the log store's neighbour), WAL, `synchronous=off`, `busy_timeout=250`,
  no foreign-key enforcement. Eloquent stays the query layer, so the
  readers keep their code; `ListingEvent` and `PageViewCount` declare
  `$connection = 'analytics'`.
- The two migrations are edited in place to build their table on the
  analytics connection. The migrations ledger lives in the app database,
  so `up()` drops the table before creating it: a rebuilt app database
  (`make fresh`, a deleted `database.sqlite`) re-runs the migration
  against an analytics file that may still hold the table, and a fresh
  app database means fresh analytics. `listing_events` keeps `listing_id`,
  `seller_id`, `customer_id` as plain indexed columns; the FKs dissolve
  with the move.
- Every analytics write is guarded: a failure of the analytics connection
  (missing directory, locked past `busy_timeout`) logs one warn line and
  the request completes. Readers are not guarded: an admin stats page or a
  seller listing detail surfaces an unavailable store as an error, the
  way any missing data source would.
- `Listing::loadEventCounts()` cannot stay a `loadCount` — that is a
  correlated subquery inside the app connection's statement. It becomes
  one grouped query on the analytics connection with the three counts
  filled in PHP.
- `MergeAnonymousCustomer` re-points `listing_events` on the analytics
  connection after the commerce transaction commits, guarded the same
  way; `CustomerOwnedTables::all()` names app-database tables only.
- Suite: `ANALYTICS_DATABASE_FILE=:memory:` in `phpunit.xml`, and
  `Tests\TestCase` lists `analytics` in `$connectionsToTransact` so
  `RefreshDatabase` keeps the migrated in-memory connection across tests
  and wraps it in the per-test transaction. Parallel-safe by construction:
  memory databases are per process.
- Staged as the ticket suggests: `page_view_counts` first (commit 1),
  `listing_events` second (commit 2), docs and alignment note (commit 3).

Alignment surface: `docs/alignment.md` gains the `ANALYTICS_DATABASE_FILE`
name and the isolation semantic; node and rails need their own tickets.

### Result

Landed on `php/analytics-store` in five commits after the promotion:

1. `88ae63f4` — the `analytics` connection and `page_view_counts`.
2. `107eebc6` — `listing_events`, the count readers, the merge re-point,
   and `ListingEvent::newRelatedInstance()` (Eloquent hands a related model
   the child's connection when the related model names none; the override
   keeps Listing, Seller, and Customer on the commerce connection).
3. `7625dca6` — docs (`docs/alignment.md` §2.6 and its §8 entry, the php
   docs, README) and the seller listings index no longer reads counts it
   never rendered (`withEventCounts` scope removed).
4. `c6439620` — Laravel's sqlite connector opens an existing file only, so
   the dev entrypoint and the deploy script touch the analytics file before
   migrating. Found by `make fresh` against the dev stack.
5. `c129a472` — the admin listing page locks its counts (Favorited stays
   the standing-favorites count via `loadCount('favorites')`), and the four
   unwritable-store tests share `Tests\AnalyticsStoreFixtures`.

Verified live on the dev stack: after `make fresh`, `database/database.sqlite`
holds neither table and `storage/analytics.sqlite3` holds both in WAL mode;
hitting `/` and `/art/{slug}` twice wrote `page_view_counts` rows and
`listing_events` rows into the analytics file only; a second `make fresh`
rebuilt both files without error. Acceptance tests listen on the query
event stream and assert no statement naming either table runs on the
default connection during a storefront request, and that the request
answers 200 with a warn line when the store is unwritable.

Decisions:

- Readers are unguarded. An unavailable store surfaces as an error on the
  admin stats, admin dashboard, and the two listing detail pages.
- `Shop\ListingController` logs a null from `RecordListingEvent` as a
  refusal whether the view collapsed or the store failed; the guard's warn
  line carries the reason.
- `synchronous=off` on the analytics connection.

Open follow-ups (outside this ticket):

- node and rails owe the same subsystem (`docs/alignment.md` §2.6);
  tickets not yet filed.
- Retention: the analytics store has no prune. `listing_events` grows with
  traffic; `page_view_counts` grows with routes × days.

## Iteration 2 — one entry point, flushed after the response

Reopened 2026-09-02. Direction: every analytics emission in the code
invokes one separate system, `App\Analytics\Analytics`; recording never
does I/O in the request; the buffer flushes after the response is sent;
each event carries the moment it was recorded, so the stored order is the
order things happened, with no reliance on an insert time.

Design:

- `App\Analytics\Analytics` (container singleton) is the only writer.
  `recordEvent(AnalyticsEventName $name, DateTimeImmutable $at, ?string
  $subjectId, ?string $actorId, array $data, ?string $dedupeKey)` and
  `recordPageView(PageViewSite $site, string $pathPattern, DateTimeImmutable
  $at)` append to an in-memory buffer and return nothing. `flush()` writes
  the buffer in one transaction on the analytics connection: events with
  `INSERT OR IGNORE` on `dedupe_key`, page views rolled up into
  `page_view_counts` with the existing upsert. A failed flush logs one
  warn line and drops the batch. `reassignActor($from, $to)` is the one
  immediate write, for the customer merge.
- Flush runs after the response for HTTP, at command end for CLI, and at
  process exit as the fallback; a buffer past its row cap flushes early.
- `listing_events` becomes `analytics_events (id 'aev', name, occurred_at,
  subject_type, subject_id, actor_id, dedupe_key UNIQUE, data JSON)`. The
  hour collapse of listing views becomes a `dedupe_key`
  (`listing:{id}:customer:{id}:hour:{bucket}`), which deletes the
  read-before-write the request did.
- Names are a closed enum, `AnalyticsEventName`: `listing.view`,
  `listing.favorite`, `listing.unfavorite`, `listing.cart_add`.
- Readers go through `App\Analytics\AnalyticsReport`; `Listing` and
  `Customer` lose their `events()` relations and the three `*_count`
  attributes, and the listing detail pages receive the counts as their own
  view variable.
- `RecordListingEvent`, `RecordPageView`, `AnalyticsWriteGuard`, and the
  `ListingEvent` model go away. The shop listing page no longer logs a
  collapsed repeat view as a refusal: nothing is written during the
  request, so there is nothing to refuse.

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

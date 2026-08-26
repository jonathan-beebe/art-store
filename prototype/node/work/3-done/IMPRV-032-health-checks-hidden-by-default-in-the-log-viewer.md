---
id: IMPRV-032
type: improvement
status: resolved
created: 2026-08-26
---

# IMPRV-032: health checks hidden by default in the log viewer

## Problem

The container healthcheck polls `GET /health` every ~10 seconds and each
poll writes a will/did pair to the log store, so the default `/admin/logs`
list buries real traffic under health noise (dev store measured
2026-08-26: 979 of 1,045 lines are `http.request`, the steady majority of
them `/health` polls).

## Goal

The default list shows the traffic a founder came to read.

## Outcome

Health-check lines are hidden from `/admin/logs` by default; a visible
filter control includes them again, and its state round-trips through the
form and pager like every other filter.

## Why it matters

Two noise lines every ten seconds outnumber the story lines between
refreshes on the page whose whole job is showing the story.

## Discovery notes

"Health check" means requests whose path is `/health` — hiding should
cover the pair and any line sharing those request_ids. The level
tiles/tallies should respect the same default so counts match the visible
list. Interacts with IMPRV-031's buyer-domain bucket if both land —
whichever lands second reconciles. The story view stays unfiltered (a
health request's story is still addressable by id).

## Related work

- FEAT-021 (e74d9d9, 0ab2f5f, fb5d9f3) — the log store and viewer

## Working (2026-08-26)

Re-validated against `37a3c21` (IMPRV-031, domain filter + grouping):
`LOG_DOMAINS`/`matchesDomain`, `countLogGroups`/`logRequestGroups`, and the
`domain`/`group` query params were exactly as the ticket describes.

**Param shape.** `health=1`, mirroring `group=1`'s precedent: absent or
empty reads as the default (hidden), `health=1` includes health-check
lines, any other value answers 400 (`optionalFilter(z.literal('1'))`, same
as `group`). Unlike `group`, this control's *default* is the filtered
state — an unchecked box submits nothing, so "absent param" and "hidden"
already coincide without special-casing the schema.

**Composition with domain.** `hideHealth` is `LogRowFilters`' own
condition, AND'd in `matchesLogRowFilters` alongside `domain`, `level`,
etc. — always applied (default `true`) rather than opt-in, so it also
covers `countLogGroups`/`logRequestGroups`, which read the same filter set.
`domain=shop` already excludes `/health` by name (IMPRV-031); the two
conditions independently agree on a health line, so `domain=shop` plus
`health=1` still shows no health-check lines — the shop bucket's own
exclusion holds regardless of the health control. Verified with a query
test (`domain=shop already excludes health, and hideHealth: false does not
undo it`).

**Tallies.** `logLevelTallies` calls `matchesLogRowFilters` with the same
`filters` the list uses (minus `level`), so the hidden-by-default rule
applies to it for free — no separate wiring needed. Verified at both the
query layer and through the route's stat-tile markup.

**Story view.** Untouched — `requestStoryRows` filters only by
`request_id` and never calls `matchesLogRowFilters`, so a health request's
story stays addressable by id regardless of the list's default. Verified
with a route test hitting `/admin/logs/requests/:requestId` for a
health-only request.

**Derivation.** `isHealthCheckRequest` is the same shape as `matchesDomain`
in `queries/log-rows.ts`: an `EXISTS` correlated on `request_id` against
the request's opening `http.request` will-line, testing `data.path =
'/health'` (equality, not `matchesDomain`'s prefix match — a health probe
has no sub-paths). A line with no `request_id` correlates to nothing and is
never treated as a health check, so CLI/boot lines are unaffected.

**A bug the default-on application surfaced.** Because `hideHealth`
defaults to `true`, its `EXISTS` subquery's `json_extract(data, '$.path')`
now runs against every `will` line's `data` on every query, including ones
that never touch `domain`. The pinned query test seeding a line with
`data: 'not json'` (a synthetic malformed-but-non-null payload, exercising
`parsedData`'s catch) started throwing `malformed JSON` from SQLite.
`matchesDomain`'s own `json_extract` in `domainPathCondition` has the same
latent fragility, dormant only because no existing test applies `domain=`
against malformed `data`. Fixed `isHealthCheckRequest` with a `case when
json_valid(data) then json_extract(...) end` guard, answering `NULL`
(no match) instead of throwing. Left `domainPathCondition` unfixed — it's
opt-in, IMPRV-031's code, and out of this ticket's scope — but the same
guard is the fix if it's ever tripped; noting here so it isn't lost.

**Existing tests.** None seeded a bare `/health`-path line outside the
domain-filter tests (which already exercise `domain=shop`/`admin`/`seller`
explicitly and are unaffected by the new default). No pinned test's
expected output changed.

**Results.** Tests: 2190 → 2204 (14 new: 6 in `log-rows.test.ts`, 8 in
`logs.test.ts`). All green via `make test`; `make check` green, coverage
99.40% lines / 95.65% branches / 99.56% functions (gate 95/90).

**Files changed.** `app/sites/admin/queries/log-rows.ts` (`hideHealth`
filter, `isHealthCheckRequest`), `app/sites/admin/routes/logs.ts` (`health`
query param, `filterFields`, `filtersOf`), `app/sites/admin/views/logs.ejs`
(checkbox), `app/sites/admin/queries/log-rows.test.ts`,
`app/sites/admin/routes/logs.test.ts`, `docs/log-store.md` (§ Viewer, new
§ The health filter), `docs/admin.md` (pages table).

**Left out.** Nothing scoped to this ticket. The pre-existing
`domainPathCondition` malformed-JSON fragility noted above is a candidate
for a follow-up ticket, not fixed here.

**Review nits closed (accept-with-nits).** Added a query test pinning that
`/healthcheck` and `/health/x` are not hidden (equality, not a prefix
match); `SHOP_EXCLUDED_PATHS` now derives its `/health` entry from
`HEALTH_CHECK_PATH` instead of repeating the literal. 2204 → 2205 tests,
`make test` green.

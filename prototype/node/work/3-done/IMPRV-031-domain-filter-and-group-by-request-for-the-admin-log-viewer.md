---
id: IMPRV-031
type: improvement
status: resolved
created: 2026-08-26
---

# IMPRV-031: domain filter and group-by-request for the admin log viewer

## Problem

`/admin/logs` (`app/sites/admin/routes/logs.ts`, `queries/log-rows.ts`)
filters on stored line fields only. (1) Nothing narrows the list to one of
the three sites — the buyer storefront, `/seller`, `/admin` — so all three
interleave, and a line's site is derivable only through its request's path
(the `http.request` will line's `data.path`); lines store no site field.
(2) The list renders one row per line, so a request's 2–30-line story
arrives interleaved with every other request's, and scanning "what
happened" per request means opening story views one at a time.

## Goal

The list reads per-site and per-request the way a founder debugs.

## Outcome

(1) A "Domain" filter, placed before Level, whose values are the three
sites; selecting one shows only lines belonging to that site's requests,
empty means all, an unrecognised value answers 400. (2) A "group logs by
request" checkbox in the filter form; checked, the list shows one row per
`request_id`, newest first, summarizing what the request did — the way
Gmail collapses a thread — and each group opens into that request's lines.
Both compose with the existing filters and survive the pager round-trip.

## Why it matters

Three sites' traffic interleaved at one row per line is the page's biggest
scan cost, and it is the page the founders refresh while debugging.

## Discovery notes

Domain derivation is the design decision: prefix-match the request's
will-line path (`/admin*`, `/seller*`, else buyer) via a join/subquery on
`request_id`, or stamp a derived column at ingest — maker's call; note the
buyer bucket catches `/health` and `/events` unless excluded, and CLI
lines have no `request_id` at all (decide whether a domain selection shows
them). Grouping changes count/paging semantics — a page counts groups;
decide the group row's summary facts (the root will/did pair carries
method, path, status, `duration_ms`) and where a group opens (inline lines
vs the existing `/admin/logs/requests/:requestId` story view, which
already renders one request). Lines with no `request_id`, grouped:
`txn_id` or singly — decide.

## Related work

- FEAT-021 (e74d9d9, 0ab2f5f, fb5d9f3) — the log store and viewer
- BUG-010 (322a7a8) — the viewer's filter round-trips surfaced it

## Working

2026-08-26

**Re-validation.** Read `docs/log-store.md`, `docs/admin.md`'s Pages table,
`app/sites/admin/routes/logs.ts`, `queries/log-rows.ts`, `views/logs.ejs`,
`views/log-story.ejs`, and their tests, plus `app/app.ts`,
`app/core/logging/request-actor.ts` (`siteActorType`, the existing live
prefix-match), `app/plugins/request-log.ts` (`data.path` is path-only, no
query string, leading slash intact), and `app/core/logging/request-id.ts`.
Confirmed: the three sites are `shopSite` (no prefix, root), `sellerSite`
(`/seller`), `adminSite` (`/admin`); `/health` is registered outside every
site; `/events` is registered per-site, so the shop's own SSE stream sits at
the storefront's unprefixed root alongside real pages.

**Decisions on the Discovery notes.**

1. *Domain derivation*: a join/subquery on `request_id`, not a stamped
   column. Implemented as a correlated `EXISTS` in `matchesDomain`
   (`log-rows.ts`) against the request's opening `http.request` `will` line,
   prefix-matching that line's `data.path` — the same rule `siteActorType`
   already applies live, so the stored view and the live one agree without a
   second source of truth. No DDL change, consistent with the store's "mirror,
   no derived columns" invariant (`docs/log-store.md` § Table).
2. *Domain vocabulary*: `shop`, `seller`, `admin` — matching
   `app/sites/shop`'s own name rather than a description like "buyer
   storefront" (direction from the human mid-task; the ticket's own prose
   said "buyer storefront" but the codebase's site is `shopSite`).
3. *The buyer/shop bucket catching `/health` and `/events`*: excluded both by
   exact path, rather than counted as shop traffic. Neither is a page a
   founder means by "shop traffic" when filtering; `/health` is infrastructure
   outside every site, `/events` is a live-update stream, not a view. `admin`
   and `seller` domains are unaffected (their own `/events` sits under their
   own prefix and is real "which site is this actor watching" traffic).
4. *CLI lines with no `request_id`*: match no domain filter. The `EXISTS`
   correlates on `request_id`; SQL's `NULL = NULL` is not true, so this falls
   out of the join rather than needing a special case — a CLI run belongs to
   no site.
5. *Grouping: the group row's summary facts*: exactly what the notes name —
   the root `http.request` will/did pair's method, path, status, and
   `duration_ms` — plus line count and last-activity `ts` (needed for
   "newest first" and useful on the row), and the closing line's own `level`
   and `msg` as the row's badge/headline. No invented "worst severity"
   aggregate.
6. *Where a group opens*: inline, a native `<details>` per group listing that
   request's lines with the same `data-line`/`data-cell` markup the story
   page uses (extracted to a shared partial, `views/partials/log-lines.ejs`,
   included from both `logs.ejs` and `log-story.ejs` — no duplicated markup,
   no JavaScript, matching the page's existing "works with JS absent" rule).
   The group header also links to the existing `/admin/logs/requests/:id`
   story page as an escape hatch for a permalink. A group opens into the
   request's *whole* story — every line it logged, not only the lines that
   matched the filter that surfaced the group — the way opening a Gmail
   thread found by search shows the whole conversation. This is deliberate:
   pinned by the query-layer test "a filter narrows which groups appear; the
   opened group still shows the whole request" and the route-level "group
   composes with an existing filter" test.
7. *Lines with no `request_id`, grouped*: singly — one group per orphan line
   — not by `txn_id`. `txn_id` grouping would fold unrelated CLI/background
   invocations that happen to touch the same transaction into one row; a
   singleton group is simpler, hides nothing (every line still appears,
   ungrouped behavior for a request-less line is unchanged), and needed no
   speculative code for a case the codebase doesn't currently exercise (no
   emitter was found writing `txn_id` without `request_id`).
8. *Paging semantics*: a page counts groups, matching the note. `countLogGroups`
   counts distinct group keys (`request_id`, or `line:<id>` for an orphan);
   `logRequestGroups` pages the *keys* by most-recent-activity-first, then
   fetches only that page's requests' full lines in one query — avoiding
   per-group N+1 queries. Group key computation itself reads the whole
   filtered set once (`id`, `ts`, `request_id` only) and groups/sorts in
   memory; accepted as the same "fine at a retention-bounded table's size"
   tradeoff the existing `msg` `LIKE` scan already relies on.

**Test approach.** TDD: wrote failing tests first in both
`queries/log-rows.test.ts` (compile-red — missing exports) and
`routes/logs.test.ts`, then implemented until green. Query-layer tests cover:
domain narrows per site, the shop domain's `/health`/`/events` exclusion, no
domain match for request-less lines, group collapsing with will/did summary
fields, orphan-line singleton groups, newest-first group ordering, group
paging, `countLogGroups`, filter-narrows-groups-but-opens-whole-request, a
malformed `data` field on the root pair not throwing (closes a coverage gap),
and domain+group composing together. Route-layer tests cover: domain narrows
the list, 400 on an unrecognised domain, empty domain reads as no filter, the
domain select is placed before level and round-trips, the group checkbox
renders one row per request and round-trips its checked state, 400 on an
unrecognised group value, grouped paging counts groups and the pager
preserves `group=1`, group composing with an existing filter, and the
grouped-empty state. No existing test was modified — `log-story.ejs`'s markup
extraction into the shared partial preserved its exact rendered output
(the pinned `<details open` assertion still passes unchanged).

**Results.** 21 new tests (11 query-layer, 10 route-layer). Full suite: 2187
tests, all green (`make test`). `make check` (lint → assets → coverage) green;
`log-rows.ts` at 100% line coverage, repo-wide 99.40% lines / 95.65% branches
against the 95/90 gate.

**Coordinator review (2026-08-26, same day).** Two gaps flagged, both closed
with additive query-layer tests in `log-rows.test.ts` (no existing test
touched): (1) the domain match is a path-segment boundary, not a bare
prefix — `/sellers/whatever` and `/administrator` were previously
unprotected against a regression to substring matching; now pinned (both
fall into `shop`, neither into `seller`/`admin`). (2) two group-summary edge
paths in `summarizeRequestGroup` were untested — an in-flight request (a
`will` line with no `did`/`failed` yet, exercising `rootMsg`'s fallback to
the opening line) and a `failed`-phase close (exercising `isRootClose`'s
`failed` branch). 3 more tests, 24 new total; full suite now 2190, all green
via `make test` (Docker).

**Left out.** No cap on a single group's inline line count (the ticket's own
"2–30-line story" framing plus the request-less-lines-go-singly decision
keeps this bounded in practice; `requestStoryRows`'s 1,000-line
`STORY_LINE_CAP` was deliberately not reused here since a group's inline
listing is a convenience view, not the authoritative story page). No
alignment.md changes — per the log-store.md doc, that amendment lands with
the store's final phase, not this ticket.

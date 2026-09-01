---
id: IMPRV-022
type: improvement
status: open
created: 2026-08-31
---

# IMPRV-022: queries slower than 50ms log source, time, and query text

## Problem
The php prototype logs only per-request query aggregates: LogRequestStory
tallies count and total time over DB::listen and emits
`data.db {queries, total_ms}` on the closing `http.request` did line
(IMPRV-017). An individual slow query is invisible — a 300ms request with
14 queries gives no way to see which query cost what, or from where it was
issued.

## Goal
A slow query announces itself in the log the moment it happens, with enough
detail to find and fix it.

## Outcome
When any database query takes longer than 50ms, the request's log story
carries a line stating the query's source, its elapsed time, and the full
query text. Requests with no slow queries log nothing extra. The threshold
is configurable with 50ms as the default. The existing did-line aggregate
is unchanged and `make check` stays green.

## Why it matters
The log story is the debugging surface; today a slow request's did line
names a total, and finding the culprit query means reproducing locally with
a profiler.

## Discovery notes
DB::listen already fires per query with sql, bindings, and elapsed time —
the boundary IMPRV-017 aggregates at; a threshold check there is the
natural site. "Source" suggestion: the first non-vendor backtrace frame,
alongside the request/txn context the logger already binds. §2.3's event
vocabulary is closed, so the new line's event name (warn level suggested;
`rate_limit.exceed` is the precedent) must land in `docs/alignment.md`,
with the node sibling ticket (node IMPRV-034, filed today) moving together
and a rails sibling seeded or a §8 entry until rails catches up. "Full
query" vs §2's redaction promise: parameterized SQL text is safe; raw
bindings can carry emails (magic-link flows) — surface the binding decision
explicitly rather than including bindings silently. Threshold env var
following the `config/log_store.php` conventions. Keep any per-query state
request-scoped.

## Related work
- IMPRV-017 (request lines carry database query count and time)
- FEAT-033 (log store and admin log viewer)
- docs/alignment.md §2 (§2.1 payload, §2.3 closed event vocabulary, redaction rules)
- node IMPRV-034 (sibling ticket, same behavior)

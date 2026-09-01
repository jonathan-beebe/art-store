---
id: IMPRV-022
type: improvement
status: resolved
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

## Working

Decisions applied, per the brief:

- `query.exceed` added to `StoryEvent`, forced to `warn` via
  `StoryEvent::level()` (the emoji prefix comes from the level, not a call
  site). Emitted with `Story::for(StoryEvent::QueryExceed)->did(...)` and no
  preceding `will` — the same standalone-`did` shape `ledger.write` already
  uses — so the top-level `duration_ms` (which means "since `will`") stays
  absent and the query's own timing lives only in `data.duration_ms`.
- `SlowQueryWatch` (`app/Support`, sibling to `DbActivity`) is the second
  `DB::listen` registration in `LoggingServiceProvider`. It is stateless —
  it reads `config('log_store.slow_query_ms')` fresh on every query and
  writes on the spot — so there is nothing to reset per request; `DbActivity`
  keeps its own tally and its own `reset()` untouched.
  `source` is the first backtrace frame outside `SlowQueryWatch.php` itself
  and outside `vendor/`.
- `LOG_SLOW_QUERY_MS` parsed by `LogSlowQueryMs` (`app/Logging`), copying
  `LogRetentionDays`'s shape exactly: positive integer or `"off"`, malformed
  refuses boot, wired into `config/log_store.php` as `slow_query_ms`.
- `data.sql` is `QueryExecuted::$sql` as Laravel already parameterizes it
  (`?` placeholders); bindings are never read.
- `docs/alignment.md`: `query.exceed` row added to §2.3, dated §8 entry
  added (php ships on IMPRV-022, node's IMPRV-034 filed, rails queued).

Files touched:
- `prototype/php/src/app/Logging/LogSlowQueryMs.php` (new)
- `prototype/php/src/app/Logging/LogSlowQueryMsTest.php` (new)
- `prototype/php/src/app/Logging/StoryEvent.php`
- `prototype/php/src/app/Support/SlowQueryWatch.php` (new)
- `prototype/php/src/app/Support/SlowQueryWatchTest.php` (new)
- `prototype/php/src/app/Providers/LoggingServiceProvider.php`
- `prototype/php/src/config/log_store.php`
- `docs/alignment.md`

Tests added (`SlowQueryWatchTest.php`, `LogSlowQueryMsTest.php`):
- `it logs a query over the threshold with its source, time, text, and the threshold itself`
- `it logs nothing for a query under the threshold`
- `it logs nothing for a query exactly at the threshold`
- `it disables the line for "off"`
- `it never carries a binding into the logged line`
- `it names the request route as the source and leaves the query count and total on the did line unchanged`
- `it parses a positive integer number of milliseconds`
- `it disables the slow-query line for "off"`
- `it refuses a malformed value and names the variable that holds it`

No deviation from the brief. `make precommit` (Pint + PHPStan + the full
suite): 3218 tests passed, 0 failures.

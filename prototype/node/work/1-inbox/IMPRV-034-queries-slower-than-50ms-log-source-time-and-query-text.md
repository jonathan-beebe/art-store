---
id: IMPRV-034
type: improvement
status: open
created: 2026-08-31
---

# IMPRV-034: queries slower than 50ms log source, time, and query text

## Problem
The node prototype's log story carries no per-query timing: nothing
observes an individual query's duration (the request-level `data.db`
aggregate required by alignment §2.2 is arriving separately on the
`node/php-alignment` branch). An individual slow query inside a request is
invisible in the story.

## Goal
A slow query announces itself in the log the moment it happens, with enough
detail to find and fix it.

## Outcome
When any database query takes longer than 50ms, the request's log story
carries a line stating the query's source, its elapsed time, and the full
query text. Requests with no slow queries log nothing extra. The threshold
is configurable with 50ms as the default. `make check` stays green.

## Why it matters
The log story is the debugging surface; today a slow request can only be
seen as a total, and finding the culprit query means reproducing locally
with a profiler.

## Discovery notes
Kysely's `log` config hook receives the compiled sql, parameters, and
queryDurationMillis per query — one instrumentation point that can serve
both this line and the §2.2 aggregate; coordinate with the
`node/php-alignment` lane C implementation so there is one tally site,
request-scoped (no module-level mutable state, per doctrine). "Source"
suggestion: the request context the child logger already binds, plus a
call-site capture outside node_modules if cheap. Same vocabulary,
redaction, and threshold notes as the php sibling: the event name is a §2.3
vocabulary addition moving with the php ticket, parameterized SQL text over
raw parameters, threshold env var defaulting to 50.

## Related work
- app/sites/admin/routes/logs.ts, app/log-story.ts, app/log-store.ts (the story and viewer this line lands in)
- branch node/php-alignment (lane C adds the §2.2 per-request db tally)
- php IMPRV-022 (sibling ticket, same behavior)
- docs/alignment.md §2 (§2.1 payload, §2.3 closed event vocabulary, redaction rules)

---
id: IMPRV-017
type: improvement
status: open
created: 2026-08-30
---

# IMPRV-017: Request lines carry database query count and time

## Problem

A request's `http.request` did line carries `duration_ms` for the whole
handler but nothing about where the time went — database work is invisible.
This session's diagnosis of the slow `/admin/accounting` page needed a
code-reading investigation just to establish the queries were NOT the cost;
the logs could not answer "was it the DB", the first question asked of any
slow request. (`app/Support/Story.php` writes the did line; the payload
contract is docs/alignment.md §2.2.)

## Goal

A slow request's own log line answers "was it the database".

## Outcome

Every `http.request` did line reports the request's database work — query
count and total query time, zero when no query ran. The values render
wherever the line's data renders (`/admin/logs` rows, the story view) and
the any-attribute filter reaches them. docs/alignment.md §2.2 records the
field PHP-first with Node and Rails owing it, the IMPRV-016 precedent.

## Why it matters

"Is it the DB" opens every slow-request investigation; today it costs a
code-reading session. On the line, the founder answers it from
`/admin/logs` directly, and N+1 or missing-index regressions surface in the
story view instead of hiding inside `duration_ms`.

## Discovery notes

Advisory, from the 2026-08-30 diagnosis: accumulate via `DB::listen`
(`QueryExecuted` carries per-query duration) and attach
`data.db = {queries: N, total_ms: X}` to the existing did write in
`Story::did` — no new event name, so §2.3's closed vocabulary stays closed.
`AccountingControllerTest` already uses `DB::listen` for query-count
assertions — the same hook. Per-query warn lines above a threshold were
considered and set aside (vocabulary change, outlier-focused); revisit only
if the aggregate proves insufficient. Parity later: knex
query/query-response events in Node, `sql.active_record` notifications in
Rails. Decide at implementation whether CLI/txn stories carry the same
field or it stays request-only.

## Related work

- FEAT-033 — log store and admin log viewer
- IMPRV-016 — request lines carry the query parameters (the §2.2
  field-addition precedent)
- RSRCH-001 — performance baseline for the PHP prototype
- DSGN-004 — log viewer redesign (durations rendered prominently)

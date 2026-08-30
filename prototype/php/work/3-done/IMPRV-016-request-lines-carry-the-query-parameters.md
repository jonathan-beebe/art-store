---
id: IMPRV-016
type: improvement
status: resolved
created: 2026-08-29
---

# IMPRV-016: Request lines carry the query parameters

## Problem
`LogRequestStory` logged `data.path` from `getPathInfo()`, which drops the query string — `/?medium=ceramic` and `/` were indistinguishable in stdout and the log store, so filtered browses and searches left no trace an operator could read.

## Outcome
- [x] The `http.request` will line carries `data.query` — the request's query parameters as an object — omitted when the URL has none. `data.path` stays the bare path, so the log viewer's domain/health path-prefix correlation reads one field unchanged.
- [x] `docs/alignment.md` §2.2 amended (`data.query`, redaction rule applies to it) with a reconciliation-log entry; PHP ships first, Node and Rails owe the field.
- [x] Searches become trackable with no new event: `/admin/logs?key=data.query.q` lists every search line; the §2.3 vocabulary stays closed.
- [x] Sidecar test covers presence and omission; live-verified in the store.

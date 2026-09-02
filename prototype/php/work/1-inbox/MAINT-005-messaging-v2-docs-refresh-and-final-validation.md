---
id: MAINT-005
type: maintenance
status: open
created: 2026-09-01
---

# MAINT-005: Messaging v2 docs refresh and final validation

## Problem
`docs/messaging.md` was written before FEAT-040 … FEAT-043 as the design of record; after the four lanes land it will describe what was intended rather than what shipped. `docs/ontology.md` still lacks the messaging entities (an open item since the first round), `docs/admin.md` and `README.md` describe the one-admin support thread, and `docs/alignment.md` §5 lists the admin messaging rows as "existing".

## Goal
The docs describe the messaging subsystem as it runs on the branch, and one full gate has run on the merged branch before the PR opens.

## Outcome
- `docs/messaging.md` reconciled against the code: every route, scope, action, and rule it names exists under that name; the "Costs stated" section lists what is still deferred.
- `docs/ontology.md` gains the messaging entities; `docs/admin.md`, `docs/architecture.md`'s Sites/messaging mentions, `README.md`, and `docs/alignment.md` §5's admin rows say what is there.
- `make check` (lint → assets → coverage) green once on the merged branch; the test count and coverage are recorded in the journal.

## Why it matters
The docs are how the next round (and the other two prototypes, which owe the same shapes) learns what the PHP messaging subsystem is.

## Related work
- FEAT-040, FEAT-041, FEAT-042, FEAT-043

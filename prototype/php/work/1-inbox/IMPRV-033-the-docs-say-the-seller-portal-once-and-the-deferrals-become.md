---
id: IMPRV-033
type: improvement
status: open
created: 2026-09-04
---

# IMPRV-033: The docs say the seller portal once, and the deferrals become tickets

## Problem
The audit (`__local__/design/seller-portal/AUDIT.md` §5, §6) found `docs/alignment.md` §8 naming seven of eleven shipped tickets, the fulfillment-flow contract written in three files, `docs/seller-portal.md` at 1,072 lines with about 300 lines of third copies and seven stale claims, `data-model.md`'s older blocks missing messaging-v2 and fulfillment columns, `ontology.md` listing four of nine analytics events, and ten follow-ups recorded only inside ticket Working sections.

## Goal
A reader finds each fact about the seller portal in one place, and every deferred item is a ticket someone can pick up.

## Outcome
- `docs/alignment.md` §8 names every shipped ticket; §4.5 alone carries the flow diagram, lane table, writers, and vocabulary consequence; `orders.md` keeps the PHP realization and links §4.5; `seller-portal.md` links both and restates neither.
- `docs/seller-portal.md` is under 700 lines, reads in the nav's order, names the flow editor and the label page, has no claim the code contradicts (audit §5 lists seven), and keeps the sections the audit names as earning their place.
- `data-model.md` matches the migrations for `conversations`, `messages`, `fulfillments`, `listings`, and lists every table whose model has `idPrefix()`; `ontology.md` lists all nine analytics events.
- Every row of audit §6 that has a definite outcome is a ticket in `work/1-inbox` (FEAT or IMPRV as fits) with a Problem, Goal, and Outcome; rows needing a product decision are listed in `__local__/design/seller-portal/DECISIONS.md` rather than filed.
- `work/journal.md` records each new ticket.

## Why it matters
The reconciliation log is how node and rails learn what they owe; a doc that says one thing three times drifts three ways; a deferral inside a Working section is a deferral nobody sees.

## Discovery notes
- `__local__/design/seller-portal/AUDIT.md` §5 and §6.
- Ticket ids come from the journal counters (`/work-write`); `DECISIONS.md` already lists which §6 rows need input.

## Related work
- MAINT-008 (the contract sweep this corrects)
- FEAT-051..061

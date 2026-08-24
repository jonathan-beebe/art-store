---
id: MAINT-003
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-003: Final validation and docs refresh for the alignment branch

## Problem
After MAINT-002, FEAT-015..021, BUG-004/005, and IMPRV-003 land, `docs/` (architecture, data-model, ontology, orders, escrow, identity, messaging, review) describe the pre-alignment code (test count, schema version, merge lists were already stale), `README.md` lists old make targets and test counts, and nobody has run the whole thing from a clean tree with the hook installed.

## Goal
The branch ships with docs that match the code and a clean-tree run that proves the commit gate, the seeds, and every route.

## Outcome
`make check` passes from a clean tree; `make fresh` seeds; every GET route from `make routes` answers without a 5xx; every doc under `docs/` and the README state what the code does after alignment, `docs/admin.md` exists, `docs/review.md` lists the known gaps that remain; the pre-commit hook is shown refusing a commit with a failing test (recorded in the ticket's Working notes).

## Why it matters
The three prototypes are compared by reading their docs and running their make targets; stale docs lose the comparison for the wrong reason.

## Discovery notes
FEAT-008/FEAT-014 are the pattern: an independent audit agent reads `docs/` against `src/app` and lists mismatches before anyone rewrites.

## Related work
- FEAT-008, FEAT-014
- docs/alignment.md

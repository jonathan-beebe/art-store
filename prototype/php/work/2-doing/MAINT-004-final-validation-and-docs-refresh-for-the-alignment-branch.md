---
id: MAINT-004
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-004: Final validation and docs refresh for the alignment branch

## Problem
After MAINT-003, FEAT-018..024, IMPRV-004/005, and BUG-003 land, `docs/` (architecture, data-model, ontology, orders, escrow, identity, messaging, review) describe the pre-alignment code (`ontology.md` already predates admin and messaging; `data-model.md` omits columns), `README.md` lists old make targets and test counts, and nobody has run the whole thing from a clean tree with the hook installed.

## Goal
The branch ships with docs that match the code and a clean-tree run that proves the commit gate, the seeds, and every route.

## Outcome
`make check` passes from a clean tree; `make fresh` seeds; every GET route from `make routes` answers without a 5xx; every doc under `docs/` and the README state what the code does after alignment, `docs/admin.md` exists, `docs/review.md` lists the known gaps that remain, and the comparison section against Node is current; the pre-commit hook is shown refusing a commit with a failing test (recorded in the ticket's Working notes).

## Why it matters
The three prototypes are compared by reading their docs and running their make targets; stale docs lose the comparison for the wrong reason.

## Discovery notes
FEAT-017 is the pattern: an independent audit agent reads `docs/` against `src/app` and lists mismatches before anyone rewrites.

## Related work
- FEAT-017, MAINT-002
- docs/alignment.md

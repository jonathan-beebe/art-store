---
id: MAINT-002
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-002: Final validation and docs refresh for the alignment branch

## Problem
After MAINT-001, FEAT-018..020, BUG-007, and IMPRV-009..012 land, `docs/` (architecture, data-model, orders, escrow, admin, identity, messaging, review) describe the pre-alignment code, `README.md` lists old make targets and test counts, and nobody has run the whole thing from a clean tree with the hook installed.

## Goal
The branch ships with docs that match the code and a clean-tree run that proves the commit gate, the seeds, and every route.

## Outcome
`make check` passes from a clean tree; `make fresh` seeds; every GET route from `make routes` answers without a 5xx; `make docs-check` renders every diagram; every doc under `docs/` and the README state what the code does after alignment, with `docs/review.md` listing the known gaps that remain; the pre-commit hook is shown refusing a commit with a failing test (recorded in the ticket's Working notes).

## Why it matters
The three prototypes are compared by reading their docs and running their make targets; stale docs lose the comparison for the wrong reason.

## Discovery notes
FEAT-017 is the pattern: an independent audit agent reads `docs/` against `src/app/` and lists mismatches before anyone rewrites.

## Related work
- FEAT-017
- docs/alignment.md

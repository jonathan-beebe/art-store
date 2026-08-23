---
id: FEAT-017
type: feature
status: open
created: 2026-08-23
---

# FEAT-017: Final validation and documentation refresh

## Problem
This ticket absorbs no findings of its own. Its problem is downstream of every other ticket in this manifest: each one changes behavior or adds a component — a `node:sqlite` dialect, a health endpoint, an outbox, SSE, a production image, CI, structured logging, validation at the boundary — that `docs/architecture.md`, `docs/review.md`, `README.md`, and `docs/data-model.md` currently describe under the pre-refinement shape. `docs/review.md`'s known-gaps list names gaps that other tickets in this batch close (the outbox gap FEAT-015 closes is one instance). Once those tickets land, the docs are stale until this one runs, and a reviewer following the README from a clean checkout would hit claims that no longer match the code.

## Goal
A reviewer can follow the README from an empty checkout to a working demo, and every claim the docs make about the system matches the code.

## Outcome
- A reviewer can run `make up`, `make test`, `make coverage`, `make image`, `make routes`, `make outbox` from the README.
- Every claim in the docs is true of the code.

## Why it matters
A reviewer's confidence in every other ticket in this batch depends on the docs and a clean run actually matching the code. A correct system with a stale README reads as an unfinished one; the gap between what was built and what is claimed is exactly what a careful reviewer checks first.

## Discovery notes
This depends on the rest of the manifest — it should be the last ticket worked, after every other ticket in this batch (BUG-002 through BUG-006, FEAT-011 through FEAT-016, IMPRV-001 through IMPRV-008, RFCTR-001 through RFCTR-004) has landed. Starting it earlier means documenting a system that is still changing under it.

Validation steps to run once everything else has landed: `docs/architecture.md`, `docs/review.md`, `README.md`, and `docs/data-model.md` need to describe the refined system accurately — the `node:sqlite` dialect, the health endpoint, the outbox, SSE, the production image, CI, structured logging, and validation at the boundary. The known-gaps list needs rewriting to drop closed gaps and state any that remain. `make docs-check` needs to pass green. A clean first run from an empty tree needs to succeed. A curl walk needs to confirm no route 500s. The smoke test needs extending to cover `/health` and the outbox.

## Related work
Depends on and follows every other ticket in this manifest: BUG-002, BUG-003, BUG-004, BUG-005, BUG-006, FEAT-011, FEAT-012, FEAT-013, FEAT-014, FEAT-015, FEAT-016, IMPRV-001, IMPRV-002, IMPRV-003, IMPRV-004, IMPRV-005, IMPRV-006, IMPRV-007, IMPRV-008, RFCTR-001, RFCTR-002, RFCTR-003, RFCTR-004.

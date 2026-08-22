---
id: FEAT-010
type: feature
status: open
created: 2026-08-22
---

# FEAT-010: Final validation — clean first run, smoke test, coverage, review, README

## Problem
Nine tickets landed from several agents in parallel. Nobody has yet run the product from an empty tree, walked every page, or checked the brief line by line against what exists.

## Goal
The prototype is demonstrably complete against the brief and a reviewer can run and judge it from the README.

## Outcome
- From an empty tree (no `node_modules`, no sqlite, no `public/app.css`), `make up` alone serves every site; every page answers 200 with the stylesheet linked.
- `make test` green: typecheck, lint, full suite; `make coverage` at or above thresholds.
- `app/test/smoke.test.ts` walks the whole product as described in `docs/architecture.md` → Testing, and `make smoke` runs it.
- Dead scaffolding deleted; any domain `if` found in a route extracted to the core.
- `docs/review.md` maps every requirement in the brief to the route and test that prove it, lists known gaps and next steps.
- `README.md` complete: run, serve, test, coverage, smoke, seeded accounts, magic links, paying, admin, messaging, known gaps.

## Why it matters
This is the deliverable the team reviews in the showdown.

## Discovery notes
Reference `prototype/rails/docs/review.md` and `prototype/rails/work/3-done/FEAT-008-final-validation.md`. A curl walk over the running server (seller, shop, admin pages; cross-actor 404s; a live guest checkout) is the acceptance.

## Related work
- FEAT-001 … FEAT-009

---
id: IMPRV-040
type: improvement
status: open
created: 2026-09-04
---

# IMPRV-040: The Fulfillment model keeps its rows and rules; pages read through adapters

## Problem
`app/Models/Fulfillment.php` is 404 lines holding four jobs (audit §3.6): transitions and row locking, admin platform aggregates, lane query scopes, and the flow projection plus display phrases, with four `loadMissing()` calls that turn a forgotten eager load into silent extra queries instead of the strict-mode exception the rest of the portal relies on.

## Goal
The model holds relations, scopes, transitions, and money accessors; page-shaped reads live in the adapters that already load their relations, and a forgotten eager load fails loudly.

## Outcome
- `platformCountsByStatus()` and `platformFees()` live in an admin reader; `itemLabel()` lives in the row adapters that build rows; `flowInEffect()`, `flowSteps()`, and `progress()` live in `OrderDetail` (or a `FulfillmentFlowReader`) which eager-loads once; `lane()` takes a `FulfillmentProgress` handed to it.
- No `loadMissing()` remains in the model; the two route-bound callers (`OrderController`, `CompleteFlowStep`) load explicitly, and the suite passes with `Model::shouldBeStrict()` catching any miss.
- The model is under about 230 lines; every behavior has the same test coverage it had.
- One commit for the whole move (the owner wants the risk isolated), preceded by any preparatory commits that change nothing observable.
- `make precommit` green; `make check` green before the PR.

## Why it matters
Strict mode only protects a codebase whose models do not quietly lazy-load; the admin aggregates and the display phrases are the ones most likely to be called from a list without their relations.

## Discovery notes
- `__local__/design/seller-portal/DECISIONS.md` decision 6 ("do this as its own commit so we can isolate the risk"). Do this after IMPRV-031 and IMPRV-032 merge; both touch the model.

## Related work
- IMPRV-031, IMPRV-032, FEAT-051, FEAT-053

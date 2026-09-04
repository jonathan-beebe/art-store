---
id: IMPRV-040
type: improvement
status: resolved
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

## Working
Characterized the model's behavior with the existing suite before touching
it, then moved each job to its own adapter in one pass:

- `App\Admin\PlatformFulfillmentReader` — `countsByStatus()` and `fees()`,
  read by `Admin\DashboardController` and `Admin\AccountingController`.
  `App\Admin` is new; `tests/Pest.php` now binds it to `CommerceTestCase`.
- `App\Support\ParcelLine` — `label()` and `imageUrl()`, read by
  `FulfillmentLanes`, `NeedsAttention`, `OrderSource`, `ThreadContext`
  (via its `order` property), and two Blade views that called the model
  method directly.
- `App\Seller\FulfillmentFlowReader` — `flowInEffect()`, `flowSteps()`,
  `progress()`, composed into `OrderDetail` (which already owned the
  eager-load) and injected into `CompleteFlowStep`, which now loads the
  flow relations explicitly before judging a submitted step.
- `Fulfillment::lane()` takes a `FulfillmentProgress` instead of building
  one; `OrderController::show()` passes the `OrderFacts` progress it
  already read.
- Deleted all four `loadMissing()` calls. `orderHasBeenPaid()` reads
  `$this->order` directly now; the one caller that did not already have it
  loaded (`Admin\OrderController::show()`, iterating `$order->fulfillments`
  for the refund form) got an explicit `fulfillments.order` eager load.

Model: 498 lines before this ticket's work (the file grew past the audit's
404-line snapshot as FEAT-051/053 and IMPRV-031/032 landed lane scopes,
row locking, and transitions that were never part of this move) → 371
lines after — every method this ticket names is gone, and the ~230-line
target undercounts the row-locking and lane-scope machinery those later
tickets added, which stays.

One commit for the move (`refactor[php]: …`), preceded by none — there was
no prep that changed nothing observable to separate out. `make precommit`
green (lint + targeted sidecars; the coordinator asked lanes to stop
running the full suite by hand while five run concurrently).

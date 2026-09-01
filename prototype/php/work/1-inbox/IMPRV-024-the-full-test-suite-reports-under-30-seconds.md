---
id: IMPRV-024
type: improvement
status: open
created: 2026-09-01
---

# IMPRV-024: the full test suite reports under 30 seconds

## Problem

The php prototype's ungated suite (composer test → pest, composer.json:62)
runs 3218 tests / 9367 assertions in 90.27s inside the container (measured
2026-09-01). It runs in a single process against sqlite :memory: with
RefreshDatabase. The per-commit gate (make precommit, IMPRV-021) runs this
suite on every commit, so each commit pays ~90s of test time plus lint.

## Goal

The full suite reports under 30 seconds, so the per-commit gate stays out of
the commit rhythm.

## Outcome

make test runs the full suite green with pest reporting a total duration
under 30 seconds. The suite's coverage stays intact — no test is deleted or
weakened to reach the number, and the make check coverage floor still passes
at 100%. make precommit and make check keep their current shapes.

## Why it matters

The gate runs on every commit and a working day holds dozens of commits; at
90s the gate taxes exactly the rhythm IMPRV-021 existed to protect.

## Discovery notes

The measure is pest's own reported Duration, so container start cost stays
outside the number. Single-process execution is the visible headroom — pest
ships --parallel (paratest), and each worker needs its own :memory:
connection, which RefreshDatabase already scopes per-process; profile first
(pest --profile) to see which tests dominate before reaching for anything
else. The coverage run's pcov cost is PR-time only and out of scope. If
parallelism lands short of 30s, the next places to look are the end-to-end
story walks (tests/SmokeTest.php) and the log-store fixtures. All advisory.

## Related work

- IMPRV-021 (the per-commit gate)
- RFCTR-001 (Pest sidecar suite)
- RSRCH-001 (performance baseline)
- IMPRV-007 (pcov instrumentation)

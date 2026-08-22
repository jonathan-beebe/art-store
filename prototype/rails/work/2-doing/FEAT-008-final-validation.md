---
id: FEAT-008
type: feature
status: open
created: 
---

# FEAT-008: Final validation, review, and end-to-end smoke

## Problem
Feature tickets were built by separate agents in parallel; their integration, the README's accuracy, the coverage target, and the full loop have not been verified as one system.

## Goal
The prototype is demonstrably runnable and testable by a team member following only the README.

## Outcome
- From a clean checkout (remove `src/vendor`, the SQLite files, `app/assets/builds`), the README's first-run steps bring the app up and `make test` is green.
- `make coverage` reports ≥ 90% on `app/domain` and ≥ 80% overall; gaps are closed or listed in README "Known gaps".
- `make smoke` runs one integration test walking: seller sign-in → create listing → mark for sale → anonymous customer views, favorites, adds to cart → guest checkout → magic link → verify → pay 4242 → seller notification and ship → customer confirms delivery → payout run → earnings shows net = 90% of price.
- `docs/review.md` maps each requirement in `__local__/prompts/initial-prompt.md` to status, routes, and the test that proves it; lists known gaps and next steps.
- Conventions hold: no domain `if`s in controllers, sidecar tests beside every non-trivial file, no JavaScript required for any flow.

## Why it matters
This is the hand-off.

## Discovery notes
Run everything inside the container. `bin/rails routes` to cross-check docs. Small fixes get a test; large ones get a BUG ticket in `work/1-inbox/` and a line in `docs/review.md`.

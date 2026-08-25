---
id: MAINT-007
type: maintenance
status: resolved
created: 2026-08-25
---

# MAINT-007: make test runs the plain suite; coverage carries the gate

## Problem
`docs/alignment.md` §6.1 (line 363) pins `test` as "the full suite, with the stack's coverage gate", so every prototype wires its gate into `make test`: node's `test` target runs `npm run coverage`, rails injects `COVERAGE_MIN=100` and the coverage boot into `bin/rails test` (`prototype/rails/Makefile:34-35`), and php's `test` runs `composer test`. MAINT-004 worked around the node half by adding a node-local `test-fast` target for the ungated loop, leaving two targets where one word should do. The user has decided the vocabulary: `test` runs tests; the gate lives with the commit gate.

## Goal
`make test` runs the plain suite in every prototype, and the coverage gate lives in `coverage`/`check`.

## Outcome
In all three prototypes `make test` runs the full suite with no coverage gate; `make coverage` runs the gated run (and still produces its report); `make check` gates commits exactly as it does today; `test-fast` no longer exists; §6.1 describes the new meanings and the three Makefiles match it.

## Why it matters
The make-target vocabulary is a cross-prototype contract — the same word must mean the same thing in all three stacks, and today "test" means "test plus a gate you didn't ask for", which MAINT-004's measurements put at a double-digit share of every everyday run. Decision made by the user on 2026-08-25.

## Discovery notes
- Node: point `test` at plain `npm test`, delete `test-fast` (added earlier on this same branch), leave `coverage` (already the gated run) and `check` (already lint → assets → coverage) untouched.
- Rails: move the `COVERAGE_MIN`/`coverage_boot` injection from `test` into `coverage`, and confirm `check` runs the gated form.
- PHP: inspect where composer's scripts put the gate (`composer test` vs a coverage script) and split along the same line.
- §6.1 rows for `test`, `coverage`, `check` need rewording; grep READMEs/docs for `make test` semantics and the `test-fast` mention MAINT-004 added. CI and the pre-commit hook run `check` — verify nothing invokes `make test` expecting the gate.

## Related work
- MAINT-004 (added `test-fast`; this ticket removes it)
- MAINT-001 (established the make vocabulary and `check` composition)

## Working
- PHP gate located: `composer test` in `src/composer.json` ran pest with
  `-d pcov.enabled=1 --coverage --min=100`; `composer test:coverage` ran the
  report with no `--min`. Split: `test` is now plain pest (no pcov), `--min=100`
  moved onto `test:coverage`, `make check` becomes `lint assets coverage`.
- Rails: `COVERAGE_MIN=100` + `RUBYOPT=-r./test/coverage_boot` moved from `test`
  to `coverage`; `check` becomes `lint assets coverage`. Plain `bin/rails test`
  still loads SimpleCov through `test_helper.rb`'s own require, ungated —
  `coverage_boot.rb` only enforces a minimum when `COVERAGE_MIN` is set.
- Node: `test` runs `npm test`; `test-fast` deleted (target + .PHONY).
- CI verified: node.yml runs `npm run check` (gate intact via the npm `check`
  script), php.yml and rails.yml run `make check`. Pre-commit hook runs
  `make check`; nothing invokes `make test` expecting a gate.
- Docs: alignment.md §6.1 rows reworded; READMEs and docs/architecture.md in
  all three prototypes updated. `docs/review.md` files left as dated snapshots.

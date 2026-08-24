---
id: MAINT-002
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-002: Common make vocabulary, RuboCop gate, CI, and a test database that resets

## Problem
`Makefile` has no `lint`, `lint-fix`, `check`, `seed`, `routes`, `payouts`, `sweep`, or `hooks` targets and there is no static-analysis gate at all (no RuboCop config — `MAINT-001`); `bin/ci` runs `test app lib` only and no GitHub workflow exists for Rails; a killed test run leaves `storage/test.sqlite3` poisoned so the next run fails without saying why (`BUG-003`). `docs/alignment.md` §6 fixes the target vocabulary and says CI and the pre-commit hook (`.githooks/pre-commit`, runs `make check`) run the same command.

## Goal
One command, `make check`, is the commit gate, the CI job, and the developer's habit, with the same target names as the other two prototypes, and a test run always starts from a database it can use.

## Outcome
Every §6.1 target exists with its meaning (`sweep` may print that it lands with FEAT-017 until it does); `make lint` runs `rubocop` with `rubocop-rails-omakase` and the tree is clean; `make check` runs lint → assets → test (with the `COVERAGE_MIN` gate) and fails on any; `.github/workflows/rails.yml` runs `make check`; `make test` prepares the test database so a killed prior run cannot poison it; `MAINT-001`'s leftovers are closed or explicitly carried; a commit touching `prototype/rails/src` with a failing test is refused by the hook installed by `make hooks` at the repository root.

## Why it matters
The commit gate and CI must be the same command or one of them lies; Rails is the only prototype without a lint gate, which the comparison will count.

## Discovery notes
`rubocop-rails-omakase` is the doctrine's choice (`__local__/prompts/rails-doctrine.md`); `bin/rails db:test:prepare` before the suite, or `maintain_test_schema` plus a truncation, fixes BUG-003. `make routes` is `bin/rails routes`; `make seed` is `db:seed`; `make payouts` is the existing rake task. Work `BUG-003` and `MAINT-001` from `1-inbox/` as part of this ticket and move them to done with it.

## Related work
- docs/alignment.md §6
- BUG-003, MAINT-001

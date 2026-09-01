---
id: IMPRV-004
type: improvement
status: open
created: 2026-08-31
---

# IMPRV-004: the commit gate pays only for what changed

## Problem
`.githooks/pre-commit` runs `make -C prototype/rails check` on every commit
touching prototype/rails outside `work/` and `docs/`: `lint` (`rubocop`),
`assets` (`tailwindcss:build`), and `coverage` (`db:test:prepare` then the
full suite with `RUBYOPT=-r./test/coverage_boot` and `COVERAGE_MIN=100`) —
three container spawns per commit (`Makefile`'s `check: lint assets
coverage`). Every PR is squash-merged, so branch-local commits vanish from
main's history — the per-commit full gate verifies intermediate states the
merge strategy discards, three containers deep, once per commit on a branch
that's typically many tiny commits.

## Goal
A commit costs one fast container running the tests; the full check runs
once, before a PR.

## Outcome
Committing a one-line source change to prototype/rails runs the test suite
ungated (`bin/rails test`, no `COVERAGE_MIN`, no SimpleCov boot), and at
most `rubocop` alongside it, in a single container spawn — with a
before/after timing table recorded in the ticket. The full `make check`
(lint → assets → coverage-gated suite) runs once per branch before a PR
opens, and a change that drops a line of coverage or fails `rubocop` is
still caught there before merge. A red test suite still blocks a commit.
`make check`, `make test`, `make lint`, and `make coverage` keep their
alignment §6.1 meanings.

## Why it matters
Sibling ticket php IMPRV-021 made the same change for the php prototype:
lint + the ungated suite per commit, in one container, with the full
coverage-gated check moving to PR time. rails has no CI workflow yet
(unlike node's FEAT-014 precedent) — before this ticket lands, rails has no
automated PR-time backstop, so either this ticket or a companion should add
one (a rails CI workflow mirroring `.github/workflows/php.yml`/`node.yml`,
or a documented manual `make check` step before opening a PR).

## Discovery notes
Reuse the docs/alignment.md §6.1 vocabulary (`test`, `lint`) for the
per-commit path rather than inventing a new target where possible; php
ended up adding a small `precommit` target since Pint+PHPStan+Pest needed
composing into one container, naming it locally (node/rails had no
precedent to match). Measure `make lint`, `make assets`, `make coverage`,
and `make test` once each before changing anything (`db:test:prepare`'s own
cost is worth isolating, since both `test` and `coverage` pay it), record
the numbers, then decide the per-commit composition.

## Related work
- php IMPRV-021 (source ticket; the commit-gate design and the root
  `CLAUDE.md` "Commit gate" section and `.githooks/pre-commit` already
  reflect the split — this ticket carries rails's own per-commit gate
  target, and the open question of a rails CI workflow)
- docs/alignment.md §6.1, §6.2 (make vocabulary and commit-gate contract)

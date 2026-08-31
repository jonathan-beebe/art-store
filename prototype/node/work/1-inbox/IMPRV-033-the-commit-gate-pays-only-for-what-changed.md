---
id: IMPRV-033
type: improvement
status: open
created: 2026-08-31
---

# IMPRV-033: the commit gate pays only for what changed

## Problem
`.githooks/pre-commit` runs `make -C prototype/node check` on every commit
touching prototype/node outside `work/` and `docs/`: `lint` (`tsc --noEmit`
then `eslint`), `assets`, and `coverage` (the full suite with V8 coverage
collection and the LCOV report) — one `npm run check` container per commit
(`Makefile`'s `check` target already composes these into a single npm
script, so node does not have the multi-container problem php had). Every
PR is squash-merged, so branch-local commits vanish from main's history —
the per-commit full gate verifies intermediate states the merge strategy
discards, once per commit on a branch that's typically many tiny commits.

## Goal
A commit costs one fast run of the tests; the full check runs once, before
a PR.

## Outcome
Committing a one-line source change to prototype/node runs the test suite
ungated (no V8 coverage instrumentation, no LCOV report), in the same single
`npm run` invocation `check` already uses today — with a before/after timing
table recorded in the ticket. The full `make check` (lint → assets →
coverage-gated suite) runs once per branch before a PR opens; CI
(`.github/workflows/node.yml`, FEAT-014) already runs `make check` again on
push/PR and is the backstop that catches a dropped line of coverage or a
type/lint failure before merge. A red test suite still blocks a commit.
`make check`, `make test`, `make lint`, and `make coverage` keep their
alignment §6.1 meanings.

## Why it matters
Sibling ticket php IMPRV-021 made the same change for the php prototype:
lint + the ungated suite per commit, in one container, with the full
coverage-gated check moving to PR time (CI already runs it there). node's
`check` is already one npm script/one container, so this ticket is narrower
than php's — the win here is dropping coverage instrumentation and the LCOV
report from the per-commit path, not container composition.

## Discovery notes
Reuse the docs/alignment.md §6.1 vocabulary (`test`, `lint`) for the
per-commit path rather than inventing a new target, the way php's
`precommit` target did (php named it locally since node/rails had no
precedent to match; consider whether node's version should share that name
or something else once both exist — an alignment question, not a unilateral
one). Measure `make lint`, `make assets`, `make coverage`, and `make test`
once each before changing anything, record the numbers, then decide what
combination the per-commit path runs (lint alone is likely cheap; V8
coverage collection overhead vs plain `node --test` is the number worth
knowing).

## Related work
- php IMPRV-021 (source ticket; the commit-gate design and the root
  `CLAUDE.md` "Commit gate" section and `.githooks/pre-commit` already
  reflect the split — this ticket carries node's own per-commit gate target)
- node FEAT-014 (CI runs `make check` on push/PR — already the PR-time
  backstop this ticket relies on)
- docs/alignment.md §6.1, §6.2 (make vocabulary and commit-gate contract)

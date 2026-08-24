---
id: MAINT-001
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-001: Common make vocabulary, `check` gate, and CI run the same command

## Problem
`Makefile` has no `lint`, `lint-fix`, `check`, `sweep`, or `hooks` targets; `make test` runs `npm run check` (typecheck + lint + coverage) under the name `test`, so the target names disagree with the other two prototypes and with `docs/alignment.md` §6.1. `.github/workflows/node.yml` runs `typecheck`/`lint`/`test:ci` as npm scripts rather than the `make check` the pre-commit hook (`.githooks/pre-commit`) runs, so the gate and CI can drift. `check` does not build assets, so a broken `app/assets/app.css` passes.

## Goal
One command, `make check`, is the commit gate, the CI job, and the developer's pre-push habit, and the target names read the same in all three prototypes.

## Outcome
`make lint`, `make lint-fix`, `make assets`, `make test`, `make check`, `make sweep`, `make routes` exist with the meanings in `docs/alignment.md` §6.1; `make check` runs lint → assets → test and fails on any of them; CI runs `make check`; a commit touching `prototype/node/src/app` with a failing test is refused by the pre-commit hook installed by `make hooks` at the repository root.

## Why it matters
The commit gate the user asked for ("a commit can't be made until all tests pass, linting is clear, and the project builds") only holds if the hook, CI, and the developer run the same command; three prototypes with three vocabularies cannot be compared by someone who runs `make check` in each.

## Discovery notes
`npm run check` already composes typecheck, lint, and coverage; `make check` can call it plus `npm run assets`. `make sweep` will call the stale-order CLI FEAT-019 adds — until then the target can print that the command lands with FEAT-019. Keep `make test` as the coverage-gated suite (it is today). The hook and root Makefile already exist on this branch; this ticket makes `make check` real for Node and switches CI to it.

## Related work
- docs/alignment.md §6
- FEAT-014 (CI)

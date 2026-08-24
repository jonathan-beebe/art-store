---
id: MAINT-003
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-003: Common make vocabulary, `check` gate, and CI

## Problem
`Makefile` has `check` (`composer check` = lint → analyse → test) but no `lint-fix`, `seed`, `routes`, `payouts`, `sweep`, or `hooks`; `check` does not build assets (`npm run build`), so a broken Tailwind input passes; there is no CI workflow for the PHP prototype at all (`.github/workflows/` has only `node.yml`). `docs/alignment.md` §6 fixes the target vocabulary and says CI and the pre-commit hook (`.githooks/pre-commit`, runs `make check`) run the same command.

## Goal
One command, `make check`, is the commit gate, the CI job, and the developer's habit, with the same target names as the other two prototypes.

## Outcome
Every §6.1 target exists with its meaning (`sweep` and `routes` may print that they land with a later ticket until they do); `make check` runs lint → assets → test and fails on any; `.github/workflows/php.yml` runs `make check` on push and pull request; a commit touching `prototype/php/src` with a failing test is refused by the hook installed by `make hooks` at the repository root.

## Why it matters
The commit gate and CI must be the same command or one of them lies; the three prototypes are compared by someone running the same `make` targets in each.

## Discovery notes
`composer check` already exists; `make assets` runs `npm run build` in the container. `make routes` is `php artisan route:list`. `make seed` is `php artisan db:seed`. CI can build the compose image and run `make check` the same way `node.yml` does.

## Related work
- docs/alignment.md §6
- MAINT-001 (static analysis gate)

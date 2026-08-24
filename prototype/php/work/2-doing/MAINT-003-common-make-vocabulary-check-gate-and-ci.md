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

## Working

Baseline before this ticket: `make test` 1111 tests, 2503 assertions, ~24s
(ungated); `make lint` (pint only) clean; `make analyse` (phpstan) 0 errors;
`make check` = `composer check` (lint → analyse → test, ungated, no assets
step).

**Makefile**: added `lint-fix`, `seed`, `routes`, `payouts`, `sweep`,
`outbox`; kept `analyse` as an extra target alongside the now-combined
`lint`. `lint` runs `composer lint` (Pint `--test`) then `composer analyse`
(PHPStan) — two `docker compose run` invocations, both `--no-deps
--entrypoint composer`. `check: lint assets test`, spelled out in the
Makefile itself rather than delegated to a composer script, so the order and
the asset build are visible in the file the hook runs. `payouts` accepts
`AS_OF=` and forwards it as `--as-of=`, matching
`RunWeeklyPayouts::$signature`. `sweep` and `outbox` print one line each and
exit 0 — the stale-order sweep lands with FEAT-020; the PHP prototype has no
outbox, notifications are in-app (`app/Notifications/`,
`config/notifications.php`).

**composer.json**: `test` now runs `pest --coverage --min=100` under pcov —
`make test` is the coverage-gated suite. Removed the `check` script (it
would otherwise disagree with the Makefile's `lint → assets → test` order
and its asset step, which composer cannot drive); `make check` is the one
place `check` is defined now. `smoke` was `composer test -- --testsuite
Smoke`, which would have inherited the new coverage gate and failed (a
single testsuite can't cover 100% of `app/`); changed to call
`vendor/bin/pest --testsuite Smoke` directly, bypassing the gated `test`
script, matching how Node's `smoke` target bypasses `npm run check`.

**Gated-test wall time**: `docker compose run --rm app php -d
memory_limit=1G vendor/bin/pest --coverage --min=100` measured at ~34s
standalone, ~54s through `make test` (docker overhead varies with load).
Full `make check` (lint ~33-37s + assets ~4-5s + gated test ~34-54s) measured
at 1:36 and 1:50 across two runs — under the ~4 minute budget, so the gate
stays on; `test` is not backed out.

**CI**: `.github/workflows/php.yml` runs `docker compose build`, then
`docker compose run --rm app true` (primes Composer/npm deps, builds
assets, migrates — the app entrypoint that `lint`/`lint-fix`/`analyse`
bypass via `--entrypoint composer`), then `make check`. Verified end to end
by rsync-ing the worktree's tracked files (excluding `vendor`,
`node_modules`, and other gitignored artifacts) into a scratch directory to
simulate a fresh checkout, then running the three steps there: `make check`
passed clean, confirming the priming step is necessary and sufficient — the
worktree's own `vendor`/`node_modules` were never touched by this
verification.

**Hook refusal**: staged a one-line comment in `Money.php` plus a
deliberately-failing test appended to `MoneyTest.php`, then `git commit`.
Observed:

```
pre-commit: make -C prototype/php check
...
Tests:    1 failed, 1111 passed (2504 assertions)
Script @php -d memory_limit=1G vendor/bin/pest --coverage --min=100 handling the test event returned with error code 1
make: *** [test] Error 1
```

The commit was refused (`HEAD` unchanged, changes stayed staged). Reverted
both files with `git restore --staged` + `git checkout --`; `git diff` and
`git status` on the two files came back clean, no trace left.

**Docs**: updated `README.md`'s command table and the `make check`/`make
lint`/`make analyse` prose, and the equivalent paragraph in
`docs/architecture.md`. Left the stale `1107 tests (2491 assertions)` counts
elsewhere in `README.md` and `docs/review.md` alone — pre-existing drift
unrelated to the make-target vocabulary, in MAINT-004's scope.

**Deviations from the contract**: none — the coverage gate stayed inside
budget, so no cut was needed.

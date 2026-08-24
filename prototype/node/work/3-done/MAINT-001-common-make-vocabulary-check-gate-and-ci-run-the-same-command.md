---
id: MAINT-001
type: maintenance
status: resolved
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

## Working

`prototype/node/Makefile` now answers every §6.1 target: `up, down, build,
logs, shell, test, smoke, coverage, lint, lint-fix, assets, check, migrate,
fresh, seed, routes, payouts, sweep, outbox`, plus the existing `docs-check`,
`image`, `run-image`. `make sweep` prints `make sweep: lands with FEAT-019,
no CLI yet` and exits 0; nothing else needed a stub line.

`src/package.json` changes:
- `lint` = `tsc --noEmit && eslint app` (was eslint only) — read-only style +
  static analysis, matching §6.1's `lint` meaning.
- `lint:fix` (new) = `eslint app --fix` — the auto-fixable subset. `tsc` has
  no auto-fix, so it is not part of this script.
- `coverage` now always runs with a `spec` reporter to stdout and an `lcov`
  reporter to `coverage/lcov.info` (folded in from the old `test:ci` script,
  which is removed). The suite behaves identically locally and in CI; the
  only difference is an lcov file nobody was reading locally now sits in the
  gitignored `coverage/` directory after every run.
- `check` = `npm run lint && npm run assets && npm run coverage` — lint,
  then assets, then the coverage-gated suite, matching §6.1's `check`
  ordering. This replaces the old `check` (`typecheck && lint && coverage`,
  no assets step — the gap the ticket's Problem section named).

`Makefile` changes:
- `test` and `coverage` both now run `npm run coverage` (previously `test`
  ran the full `npm run check`, which is the overload the ticket's Problem
  section flagged — `test` doing lint and typecheck under a name that in
  every other prototype means just the suite). `test` and `coverage` are
  identical today; there was nothing left to split an HTML-only variant out
  of once `coverage` started always writing lcov, so I did not invent one.
  §6.1 describes `coverage` as producing "an HTML/LCOV report" — this
  delivers the LCOV half; there is still no HTML report (no `genhtml` or
  equivalent in the image). That gap predates this ticket and adding it is
  out of scope here; flagging as an open item below.
- `lint`, `lint-fix`, `check` added, each a thin `docker compose run` wrapper
  over the matching npm script.
- `sweep` added as the FEAT-019 stub described above.
- `.PHONY` updated to include `lint lint-fix check sweep`.

**Decision — `npm run check` vs `make check`:** `make check`'s recipe is a
single `docker compose run --rm app npm run check` (no assembly of separate
`make lint` / `make assets` / `make test` calls at the Makefile level). CI
has no compose stack, so it cannot run `make check` through Docker the way
the pre-commit hook does; instead `.github/workflows/node.yml` runs the
identical `npm run check`, then uploads the `coverage/lcov.info` that run
already wrote. The hook and CI now literally run the same command inside
their respective environments (`make -C prototype/node check` /
`npm run check`), which is the "cannot disagree" requirement in the ticket
Outcome and contract §6.2, solved as the ticket's own step 4 suggested.

**Verified:**
- `make lint`, `make assets`, `make sweep`, `make routes` — all exit 0.
- `make check` — exit 0, lint clean, `public/app.css` rebuilt, suite green:
  **1536/1536 tests pass, 0 fail**, coverage **99.57% lines / 97.22% branches
  / 99.47% functions** (unchanged from baseline — no test or source file
  under `app/` touched by this ticket). `src/coverage/lcov.info` written.
- Commit-gate proof: staged a one-line change to
  `src/app/core/money.test.ts` (`addCents(cents(1000), cents(500))` expected
  changed from `1500` to `999999`), then `git commit`. `.githooks/pre-commit`
  ran `make -C prototype/node check`, which ran the full check and failed on
  the deliberately broken assertion:

  ```
  ✖ failing tests:

  test at app/core/money.test.ts:18:1
  ✖ addCents sums two amounts (1.381667ms)
    AssertionError [ERR_ASSERTION]: Expected values to be strictly equal:

    1500 !== 999999

  make: *** [check] Error 1
  ```

  The commit was refused (`git commit` exited 1, no commit created —
  confirmed `git log` still showed the prior HEAD). The test file was then
  reverted with `git checkout -- src/app/core/money.test.ts`; no breakage
  left in the tree.

**Deviations / open items:**
- `make coverage` does not produce an HTML report, only LCOV — §6.1's literal
  wording ("HTML/LCOV report") is only half satisfied. No HTML tooling
  (`genhtml`, `nyc`, `c8`) is present in the image, and adding one is outside
  this ticket's Outcome bullets (make vocabulary, `check` gate, CI parity).
  Left for a follow-up if an HTML report is wanted.
- `test` and `coverage` are now identical targets/scripts. Kept both because
  §6.1 lists them as separate rows with separate meanings across all three
  prototypes; Node just has no daylight between "the suite with the gate"
  and "the suite with the gate plus a report" once the report is unconditional.
- Historical evidence in `docs/review.md`'s "Verified on FEAT-017" section
  (quoting the old `make test` / `npm run check` behavior) was left as-is —
  it is a dated verification record, not a description of current vocabulary.

### Fix-up

The Working section above claimed the Makefile answered every §6.1 target
including `smoke`, but the `smoke:` recipe was dropped from the file during
editing — only its name survived, in `.PHONY`. `make smoke` had no rule and
would have fallen through to Make's "nothing to be done" or a build error.
Restored the recipe at its original position (between `test` and `coverage`):

```
smoke:
	docker compose run --rm app node --test app/test/smoke.test.ts
```

No `--disable-warning=ExperimentalWarning` needed — running it plain
produces no experimental-warning noise, so the flag other node invocations
in this Makefile use was not added here either.

Verified after restoring: `make smoke` — 8/8 tests pass, exit 0. `make lint`,
`make lint-fix`, `make sweep`, `make routes` — all exit 0, unchanged.
`make check` — exit 0, 1536/1536 tests, 99.57% lines / 97.22% branches /
99.47% functions, unchanged from baseline.

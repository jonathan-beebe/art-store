---
id: IMPRV-021
type: improvement
status: resolved
created: 2026-08-31
---

# IMPRV-021: the commit gate pays only for what changed

## Problem
Every commit touching prototype/php outside work/ and docs/ runs the full
`make check` (.githooks/pre-commit:12): five fresh containers
(Makefile:44-59) covering lint, asset build, and the coverage-gated suite
with its HTML report. The workflow is many tiny iterative commits and every
PR is squash-merged, so branch-local commits vanish from main's history —
the per-commit full gate verifies intermediate states the merge strategy
discards, N times per branch. Inside the gate, work repeats that has not
changed: PHPStan at level max recomputes the whole tree from cold every run
because its result cache lives in the discarded container's /tmp
(src/phpstan.neon sets no tmpDir); Pest runs the ~3200-test suite in a
single process (composer.json test:coverage); the gated run renders the full
HTML coverage report on every commit though the gate consumes only the
--min=100 number.

## Goal
A commit costs one fast container running the tests; the full check runs
once, before a PR.

## Outcome
Committing a one-line source change to prototype/php runs the test suite
(ungated), and at most lint/format alongside it, in a single container
spawn — with a before/after timing table recorded in the ticket. The full
`make check` (lint → assets → coverage-gated suite) runs once per branch
before a PR opens, and a change that drops a line of coverage or fails
static analysis is still caught there before merge. A red test suite still
blocks a commit. `make check`, `make test`, `make lint`, and `make coverage`
still answer the alignment §6.1 vocabulary.

## Why it matters
The owner optimizes for tiny iterative commits (the php/optimize run was 44
commits — 44 full-gate runs). A multi-minute per-commit gate taxes exactly
the workflow the repository wants to encourage, and under squash merges the
tax buys verification of commits that never reach main's history.

## Discovery notes
Two layers, and the first dominates: when the gate runs, then how fast each
stage is.

Gate placement (owner decision, 2026-08-31): per commit — tests at minimum,
maybe lint/format, in one container rather than five; full check — at PR
time. The pre-commit hook is repo-level (.githooks/pre-commit covers all
three prototypes) and the policy is documented in the root CLAUDE.md
"Commit gate" section, so the hook, that text, and sibling expectations move
together; this ticket can carry the php path and the hook change, seeding
node/rails siblings for their fast paths. Candidate pre-PR gate sites: the
PR-creation skill (my-git-create-pull-request) running `make check`, a
pre-push hook, and CI as backstop (node already runs check in CI,
FEAT-014; php/rails have no CI ticket yet). Reusing the §6.1 vocabulary
(`test`, `lint`) for the per-commit path avoids inventing new make targets;
a single composer script composing them would keep it to one container —
advisory, the maker chooses the shape.

Stage-speed levers, still worth having because the per-commit test run and
the pre-PR check both benefit; expected value order:

1. Pest ships ParaTest — a parallel run across the container's cores
   commonly cuts a suite this size 3-5×, with per-process SQLite databases
   via Laravel's TEST_TOKEN integration as the isolation risk to test for.
   First-order now: the suite is the per-commit cost.
2. Persist PHPStan's result cache across ephemeral containers by pointing
   tmpDir into the bind-mounted src tree (gitignored) — level-max cold vs
   warm is typically minutes vs seconds, one line of config. Matters if
   lint joins the per-commit path, and for the pre-PR run either way.
3. The HTML report in the gated run is unread outside a human `make
   coverage`; §6.1 defines `coverage` as producing the report, so either
   the pre-PR gate composes a reportless coverage variant (an alignment
   question — all three prototypes move together) or the report stays.
4. `lint` spawns two containers where one `composer lint && composer
   analyse` run would do.

Measure first — time each stage once to rank; expected order is coverage
suite ≫ cold PHPStan > assets > pint ≈ container spawn overhead. Gating
commits on ungated `test` (no coverage) is now the design, with the 100%
guarantee moving to the pre-PR gate. During-work verification stays agent
judgment: run `make test`/`make analyse` in the moment as changes warrant.
Sequence the work after branch php/stateless-badge lands — it must pass the
gate as it exists today.

## Related work
- MAINT-001 (static-analysis and lint gate)
- BUG-004 (check gate red on fresh checkout)
- RSRCH-001 (performance baseline)
- node FEAT-014 (CI runs check and coverage on node 24 — the CI backstop precedent)
- docs/alignment.md §6.1 (make vocabulary the gates must keep answering)
- node IMPRV-033, rails IMPRV-004 (seeded fast-path siblings)

## Working

### Before (cold, measured on this branch before any change)

| Stage                                      | Time    | Containers |
| ------------------------------------------- | ------- | ---------- |
| `lint` (pint --test, then phpstan analyse)  | 54.9s   | 2          |
| `assets` (vite build)                       | 5.2s    | 1          |
| `coverage` (pcov, --min=100, HTML report)    | 149.1s  | 1          |
| implied `check` (sum of the three above)     | ~209.2s | 4          |
| `test` (ungated, no coverage) — reference   | 72.6s   | 1          |
| `analyse` alone (PHPStan, no result cache)   | 49.8–57.0s | 1       |

Coverage dominates, matching the ticket's prediction (coverage suite ≫ cold
PHPStan > assets). `make check` as actually run by the pre-commit hook before
this ticket costs ~209s across 4 container spawns, on every commit.

### Changes made

1. **PHPStan result cache persisted** (`src/phpstan.neon`): `tmpDir:
   .phpstan-cache`, gitignored, inside the bind-mounted src tree instead of
   the ephemeral container's `/tmp`. Cold run 49.8–57.0s; warm run 8.7s
   (~85% faster) once a prior run has populated the cache — which now
   survives every subsequent `docker compose run`.
2. **Lint composed into one container spawn**: `composer.json` gains
   `lint:all` (`@lint` then `@analyse`); `make lint` runs it via one
   `docker compose run` instead of two. Same checks, same exit behavior,
   half the container spawns.
3. **Per-commit gate**: new `make precommit` target — `docker compose run
   --rm app sh -c "composer lint:all && composer test"`, one container,
   lint (Pint + PHPStan, warm-cached) then the full suite ungated (no pcov,
   no `--min`, no HTML report). `.githooks/pre-commit` calls this for php
   instead of `make check`; node and rails are untouched (still run their
   full `check` per commit) until their seeded siblings land.
4. **Full `make check` stays intact** — same target, same meaning (lint →
   assets → coverage) — but now runs once per branch before a PR rather
   than on every commit. It also got faster as a side effect of #1/#2 (see
   After table).

### After

| Stage / target                                          | Time         | Containers |
| --------------------------------------------------------- | ------------ | ---------- |
| `lint` (combined, warm PHPStan cache)                      | 24.1s        | 1          |
| `precommit` (lint:all + test, ungated, warm cache) — **new php per-commit gate** | 87–135s (representative clean run 87.1s; the range reflects load noise on a shared laptop, not the gate itself) | 1 |
| `check` (full gate, warm cache + combined lint) — **new pre-PR gate** | 154.6–163.8s (three runs) | 3 |

Per-commit cost: ~209s / 4 containers → ~87–135s / 1 container. The coverage
stage (pcov instrumentation, `--min=100`, HTML report — the largest single
line item at 149s) no longer runs on every commit at all; it runs once per
branch via `make check` at PR time. `make check`/`make test`/`make
lint`/`make coverage` are unchanged in meaning (docs/alignment.md §6.1).

### Where the full gate lives now

CI (`.github/workflows/php.yml`) already runs `make check` on every push and
pull_request — this predates the ticket (MAINT-003) and needed no change.
Since every merge to `main` goes through a squash-merged PR, opening the PR
triggers CI's `make check`, which is the enforcement point that catches a
coverage or static-analysis regression before merge. No pre-push hook was
added: CI already covers every PR unconditionally of what ran locally, and a
local pre-push hook would just pay the full-check cost a second time on the
same box for no extra safety. A contributor who wants the full gate's answer
before pushing still has `make check` to run by hand.

### Pest `--parallel`: tried, not adopted

Attempted the ticket's first-ranked lever (ParaTest, `pest --parallel`,
8 processes — this container's `nproc`). `--parallel` alone fails outright:
child processes don't inherit `-d memory_limit=1G`, fixed with
`--passthru-php="'-d' 'memory_limit=1G'"`. With that fixed, the suite still
fails reproducibly: 5 of 3204 tests red, every time, all in
`App\Logging\LogStoreConfigTest` and
`App\Support\RateLimiting\RateLimitsConfigTest` — reproduced in isolation
(`--filter` to just one of those files still fails under `--parallel`, passes
under plain `pest`). Root cause traced to `Illuminate\Support\Env::getRepository()`:
both files mutate it (`->set()`/`->clear()`) to exercise `LOG_RETENTION_DAYS`/
`RATE_LIMIT_*` config parsing (`.env` ships real values for both); under
ParaTest's process wrapper, `->set()` on the (immutable-mode) repository is a
no-op, so the config file under test always reads back the real `.env`/default
value instead of the test's override. No other files touch
`Env::getRepository()` for these keys, so the blast radius is exactly these 5
tests, not a broader isolation problem (the ticket's specific SQLite/TEST_TOKEN
worry doesn't apply here — the suite's `:memory:` sqlite is already
process-isolated).

Decision: **not adopted in this ticket.** Landing `--parallel` today would
make these two files flake red on unrelated changes, which fails the outcome's
"a red test suite still blocks a commit" the wrong way — a false red. The
per-commit gate stays on plain (sequential) `composer test`, satisfying the
outcome's "one container running the tests" without it. Follow-up (not filed
as a separate ticket; recorded here for whoever picks up parallel next):
rewrite `LogStoreConfigTest`/`RateLimitsConfigTest` to stop depending on
`Env::getRepository()`'s mutability under a subprocess-per-file runner (e.g.
assert `LogRetentionDays::parse()`/the rate-limit parser directly against
literal strings, the way `LogRetentionDaysTest` already does, rather than
round-tripping through `config_path()` and a mutated env repository) before
turning `--parallel` on.

### §6.1 question flagged, not resolved

Lever #3 (HTML coverage report unread outside a human `make coverage`) is
left alone: `coverage`'s meaning (§6.1: "the suite with the stack's coverage
gate and its HTML/LCOV report") is unchanged, and no reportless variant was
added. This is a three-prototype alignment decision, not a php-local one —
flagged here for whoever next touches §6.1, not made unilaterally.

### Hook validation note

`core.hooksPath` (`git config core.hooksPath`) resolves to the **main
checkout's** `.githooks`, shared across all worktrees — editing this
worktree's `.githooks/pre-commit` does not change what actually runs for
`git commit` here (every commit on this branch, including the ones that
change the hook script itself, still ran the *old* full-check hook, and
stayed green under it). The new script's logic (routes php to `make
precommit`, leaves node/rails on `make check`, still skips work/docs-only
changes) was validated by running `sh .githooks/pre-commit` directly against
the staged diff (exit 0, correctly invoked `make -C prototype/php
precommit`) rather than via a live `git commit`. It takes effect for real
once this branch reaches a checkout whose `core.hooksPath` points at it
(after merge, for the main checkout).

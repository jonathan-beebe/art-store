---
id: IMPRV-021
type: improvement
status: open
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

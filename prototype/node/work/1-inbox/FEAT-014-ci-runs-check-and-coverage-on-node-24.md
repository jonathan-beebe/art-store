---
id: FEAT-014
type: feature
status: open
created: 2026-08-23
---

# FEAT-014: CI runs check and coverage on Node 24

## Problem
No `.github/workflows` directory exists anywhere in the repo. `package.json`'s `check` script is `npm run typecheck && npm run lint && npm test`; `coverage` is a separate script (`node --test --experimental-test-coverage … --test-coverage-lines=90 --test-coverage-branches=80`). The `Makefile`'s `test:` target runs `npm run check`; `coverage:` is a separate target. Nothing runs either on a push or a PR, so the only gate that enforces a coverage floor is a script nobody's pipeline runs, and `make test` / `npm run check` pass regardless of coverage level. Verified baseline: `npm run coverage` reports 1161 tests, 0 fail, 10.6s, 99.42% lines / 95.23% branches / 98.85% funcs — comfortably above the 90/80 thresholds actually configured.

## Goal
Every push and PR touching `prototype/node` proves the test claims in the docs are true, automatically.

## Outcome
- A GitHub Actions workflow under the repo root runs typecheck, lint, tests, and the coverage gate for `prototype/node` on Node 24, on every push/PR touching it.
- Coverage thresholds are raised to 95 lines / 90 branches.
- `npm run check` enforces coverage.

## Why it matters
No prototype in this repo has CI — not Node, not Rails, not PHP — so a workflow here is uncontested credibility, not a defensive catch-up. It turns the "1,161 tests / 99.42% coverage" claim in `docs/review.md` into a live badge instead of a paragraph a reader has to take on faith. The doctrine line is already satisfied by tooling that exists: `node --test --experimental-test-coverage --test-coverage-lines=90 --test-coverage-branches=80` is in `package.json` today — this ticket is the platform's own test runner gating the build, not new tooling.

## Discovery notes
Fold the coverage flags into `test` (or have the workflow run both `check` and `coverage`) — the run costs about 10 seconds, so there is no reason coverage stays opt-in. Raise the floor to 95/90 to sit close to the actual 99.4/95.2, so a real regression shows up instead of being absorbed by slack in the gate.

`node:test` stacks reporters for readable CI output plus machine-readable artifacts with no added dependency, e.g. combining `--test-reporter=spec` to stdout with `--test-reporter=junit` and `--test-reporter=lcov` to files (lcov emits coverage only, so pair it with a second reporter for pass/fail output). `--test-shard=i/n` exists if the suite ever outgrows one runner — not needed at today's size.

State stability accurately in the README rather than overclaiming: `node:test` itself is stable, but `--experimental-test-coverage` is still required in Node 24, and the `--test-coverage-lines`/`-branches` threshold flags are still marked Stability 1 (Experimental). The honest claim is "the platform's own runner gates the build," not "no experimental flags."

Files expected to touch: new `.github/workflows/node.yml`, `package.json` (`check`/`coverage` scripts), `README.md` (accurate stability language).

No hard landing-order dependency on the other FEAT-011–013 tickets, though running this after FEAT-012 (the `node:sqlite` dialect swap) means CI exercises the data layer the project is actually shipping rather than the one being replaced.

## Related work
- 06-tests-views.md — "`check` does not run coverage, and there is no CI at all"
- 07-showcase.md — showcase opportunity #4 (CI workflow: `npm run check` + coverage gate on Node 24)

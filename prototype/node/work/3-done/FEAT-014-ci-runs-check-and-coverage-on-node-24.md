---
id: FEAT-014
type: feature
status: resolved
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

## Working

Problem re-validated against the code as described: no `.github` directory existed, `check` ran plain `test`, thresholds were 90/80. Proceeded as scoped.

**Changed:**
- `.github/workflows/node.yml` (new, repo root) — job `check` on `ubuntu-latest`, `defaults.run.working-directory: prototype/node/src`. Triggers on push to `main` and on `pull_request`, both filtered to `paths: [prototype/node/**, .github/workflows/node.yml]`. Steps: `actions/checkout@v4`, `actions/setup-node@v4` (`node-version: '24'`, `cache: npm`, `cache-dependency-path: prototype/node/src/package-lock.json`), `npm ci`, `npm run assets` (smoke test serves `/app.css`), `npm run check`, `npm run test:ci`, then `actions/upload-artifact@v4` for `prototype/node/src/coverage/lcov.info`.
- `prototype/node/src/package.json` — `coverage` thresholds raised 90/80 → 95/90. New `test:ci` script: same coverage run, stacked `--test-reporter=spec --test-reporter-destination=stdout --test-reporter=lcov --test-reporter-destination=coverage/lcov.info`, prefixed with `mkdir -p coverage &&` (the lcov reporter does not create its own output directory — `ENOENT` without it; `coverage/` isn't checked in, so nothing else creates it first). `check` now runs `typecheck && lint && coverage` (was `... && npm test`). `test` is untouched — still the fast, ungated run.
- `prototype/node/Makefile` — one-line comment above `test:` noting `make test` now gates on the raised coverage floor, since `check`'s meaning changed.
- `prototype/node/README.md` — new short `## CI` section describing the workflow; updated the `## Tests` section's description of what `check` runs (coverage-gated suite, not plain `node --test`) and noted `npm test` as the fast ungated alternative; updated the `## Coverage` section's threshold numbers (90/80 → 95/90) and added the stability paragraph from the ticket's discovery notes (`node:test` stable, `--experimental-test-coverage` and the threshold flags still Stability 1).

**NODE_OPTIONS handling:** mirrored the existing pattern — each script sets `NODE_OPTIONS=--disable-warning=ExperimentalWarning` inline before `node --test`, same as `test`/`coverage` already did. No workflow-level env needed; `actions/setup-node` with `node-version: '24'` resolves to a current 24.x same as the local 24.12 install.

**Left alone, deliberately:**
- `prototype/node/docs/architecture.md` — its Testing table still says `--test-coverage-lines=90 --test-coverage-branches=80` and describes `check` as "typecheck, then lint, then the suite." Both are now stale. Not in this ticket's territory (docs/ wasn't listed), so left for a separate pass rather than touched here.
- Two-run design in the workflow (`npm run check` then `npm run test:ci`) is intentionally redundant: `check` calls the `coverage` script (per the ticket's explicit script wiring) which has no reporter flags and writes no report file, so `test:ci` reruns the same gated suite a second time (~9-10s) purely to produce `coverage/lcov.info` for the upload step. Flagging this in case a single-run design was intended instead — `check` could call `test:ci` rather than `coverage`, at the cost of `check` always writing a coverage dir even outside CI.

**Verification:** Could not get a fully green `npm run check` at the moment of testing — two other tickets' uncommitted, concurrent WIP under `app/**` (RFCTR-002 messaging naming, and edits touching `app/core/customers/customer-merge-plan.ts`) currently fail `tsc --noEmit` with unrelated type errors. Confirmed this is not caused by anything in this ticket's territory: `npm run lint` passes clean in isolation; `npm run coverage` and `npm run test:ci` both ran clean against the 95/90 thresholds once the tree briefly settled (another worker's in-flight breakage in `day-label.ts` resolved mid-session) — 1,292 tests, 0 fail, ~10s, 99.68% lines / 95.37% branches / 99.80% funcs, comfortably above the new floor. `npm run test:ci` confirmed to write a well-formed `coverage/lcov.info` (19,247 lines). Branch coverage did not need lowering — stayed well above 90 throughout. YAML validated by hand and via Ruby's `YAML.load_file` (parses cleanly; unquoted `on:` reads as a boolean key under YAML 1.1, which is the universal, harmless convention GitHub's own parser special-cases — not a real defect). `actionlint` is not installed on this machine; skipped.

---
id: MAINT-004
type: maintenance
status: resolved
created: 2026-08-25
---

# MAINT-004: Everyday test run drops the coverage gate and warms caches

## Problem
Three separate cold costs land on every local loop:

1. `make test` is the coverage run (`Makefile` `test:` → `npm run coverage`) — V8 precise block coverage enabled in each of the 252 test-file processes, plus include/exclude filtering over `app/**` and lcov+spec reporting, on every run. The plain `npm test` script exists (`package.json:11`) but no make target exposes it, and the repo rule is make targets only.
2. Nothing sets `NODE_COMPILE_CACHE`, so the 252 processes each re-parse and re-compile their whole import graph from scratch — 62 of them import the entire app (fastify, kysely, zod, ejs, pino, every site). Node 24's on-disk compile cache would share that work across processes and across runs via the bind mount.
3. `check` type-checks the codebase twice from cold every run: `tsc --noEmit` builds a full program, then typescript-eslint's `projectService` (`eslint.config.js:13-15`) builds a second one. `tsconfig.json` has no `incremental`/`tsBuildInfoFile`, and the lint scripts pass no `--cache` to eslint, so neither pass persists anything — even though the bind mount would carry cache files across the ephemeral `docker compose run` containers.

## Goal
The everyday loop pays for tests, not for gates and cold caches.

## Outcome
A make target runs the suite without the coverage machinery; repeated `make lint`/`make check` runs reuse warm type-check and lint caches that survive container churn; `make check` keeps exactly its current guarantees for the commit gate and CI.

## Why it matters
These are the highest ratio wins found — configuration lines that remove double-digit percentages of every test and check run without touching a single test or source file. The commit gate runs on every commit that touches the prototype, so its latency is a tax on all work.

## Discovery notes
- Coverage overhead is typically 15–40% of suite wall-clock before reporting; the gate stays where it belongs (`check`, CI, the hook).
- `NODE_COMPILE_CACHE` pointed somewhere bind-mounted (or a compose `environment:` entry) engages for the dependency graph at minimum; verify what it does for type-stripped `.ts` files.
- `"incremental": true` + `tsBuildInfoFile` in tsconfig; `eslint --cache --cache-location` in the lint scripts. The tsc/eslint double type-check is structural (eslint does not report type errors), so the caches are the win, not removing a pass.
- A later, separate lever: `--test-isolation=none` as an opt-in fast lane — audit the few tests touching `process.env` first. Out of scope here.

## Related work
- MAINT-001 (established the make vocabulary and the `check` composition)
- MAINT-005 (the fixture-cost half of test wall-clock)

## Working

### Changes
- `Makefile` — new `test-fast` target (`npm test` in the container), added to `.PHONY`; `test` comment now points at `test-fast` for the everyday loop.
- `docker-compose.yml` — `NODE_COMPILE_CACHE: /var/www/src/.cache/node-compile-cache` in the service environment; on the bind mount so the cache survives `docker compose run --rm` churn.
- `src/tsconfig.json` — `"incremental": true`, `"tsBuildInfoFile": ".cache/tsconfig.tsbuildinfo"` (works with `noEmit` on TS 5.9).
- `src/package.json` — `eslint --cache --cache-location .cache/eslint/` in `lint` and `lint:fix`; `coverage` prepends `NODE_DISABLE_COMPILE_CACHE=1`.
- `.gitignore` — `src/.cache/`.

### Alignment decision
`docs/alignment.md` §6.1 pins `test` = "the full suite, with the stack's coverage gate". `test` keeps that meaning; the fast lane is the new `test-fast` target, node-local the way `docs-check` already is (present in the Makefile, absent from the contract table). `alignment.md` unchanged.

### Compile cache verification
`NODE_DEBUG_NATIVE=COMPILE_CACHE` shows the cache engages fully for type-stripped `.ts`: each app module gets a `StrippedTypeScript` entry (the amaro transpile output) plus a V8 code-cache entry for the resulting ESM, and both read back "accepted" on warm runs (2405 accepted entries on a warm CLI trace). The dir populates at ~2.1k files / 11MB after one suite run; the key includes Node version, arch, and uid, so `make` (host uid) and bare `docker compose run` (uid 1000) populate separate subdirectories.

Found during validation: code restored from the V8 compile cache reports different precise-block-coverage counts than freshly compiled code — with the cache live, the gate's numbers drifted 99.43/95.86 → 99.48/95.62 run-to-run. `NODE_DISABLE_COMPILE_CACHE=1` in the `coverage` script pins the gate's measurement; every post-change coverage run reproduces 99.43/95.86/99.38 exactly.

### Measurements (Docker, macOS arm64 host; run-to-run noise ±5s on the suite)
| Run | Before | After |
| --- | --- | --- |
| `make test` (coverage) | 52.4s, 56.4s | 42.5s, ~52s (unchanged path; noise) |
| `make test-fast` | — (target did not exist); bare `npm test` 49.1s, 53.6s | 48.9s, 51.8s, 53.5s warm |
| back-to-back pair | `test` 56.4s | `test-fast` 48.9s (−13%) |
| cold cache-writing suite run | — | 63.0s (one-time) |
| `make lint` second run | 23.5s (no caches to warm) | 7.5s (−68%) |
| `make check` warm | ~80s (sum of parts: lint 24.5 + coverage 52.4 + assets; not timed pre-change) | 53.2s, 58.8s |

The suite-side compile cache is wall-clock neutral on this host — bind-mount I/O for the cache files offsets the compile savings, and the deltas sit inside the noise band. The unambiguous wins: the coverage machinery off the everyday run (~7s on the back-to-back pair) and the warm lint caches (−17s on every `lint`, carried into every `check`).

### Validation
- `make check` green, exit 0: 2022/2022 tests, coverage 99.43/95.86/99.38, gate 95/90 intact.
- Out of scope, untouched: `--test-isolation=none` (per ticket).

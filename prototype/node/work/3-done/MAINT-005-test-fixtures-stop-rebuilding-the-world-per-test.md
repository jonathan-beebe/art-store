---
id: MAINT-005
type: maintenance
status: resolved
created: 2026-08-25
---

# MAINT-005: Test fixtures stop rebuilding the world per test

## Problem
`buildTestApp` (`app/test/build-test-app.ts:193-222`) opens a fresh `:memory:` database, runs the full migrator (`migrateToLatest` — readdir of `app/db/migrations/`, kysely bookkeeping, 13 migration modules totaling ~759 lines of DDL), then builds the whole Fastify app and awaits `ready()`. `openCommerceWorld` (`app/test/commerce-world.ts:48-60`) runs the same migrate. No test file uses `before`/`beforeEach`; each `test()` calls a fixture inline. Measured across the suite: 481 `await buildTestApp` calls in 62 files and 272 `await openCommerceWorld` calls in 69 files — roughly 753 full migration runs and 481 full app constructions per suite run, for 1,883 tests. The heaviest file pays it 32 times in one process.

Smaller cost in the same path: every `buildTestApp` call does `mkdtemp` + `mkdir` and a recursive `rm` at close (`:201-213, 232-236`) for upload/outbox directories the fixture's own comment (`:141-142`) notes most tests never read.

## Goal
A test pays for its scenario, not for rebuilding the schema and the app.

## Outcome
Schema setup executes a bounded number of times per test process regardless of how many tests the file holds; databases remain in-memory and isolated per test; no mocks enter the data layer; suite wall-clock drops measurably and `make check` keeps its gates.

## Why it matters
This is the dominant repeated cost inside the suite — three-quarters of a thousand identical DDL replays per run, plus the same Fastify plugin tree registered 481 times. It compounds with every future test added, and it is contained: both fixtures live in two files, and no test needs to change.

## Discovery notes
- Per-process schema template: migrate one `:memory:` DB once per process, dump `sqlite_master` DDL, memoize the string, and have each fixture open a fresh `:memory:` DB and apply it with a single `exec` — the migrator, readdir, and per-statement round trips drop out of the per-test path. `node:sqlite`'s backup API against a once-built template is the alternative shape.
- Temp directories can be lazy or opt-in; the handful of upload/outbox suites already pass config overrides.
- A memoized app per process for tests that pass no overrides is a second lever; measure whether the DDL snapshot alone suffices before reaching for it.

## Related work
- MAINT-004 (the process/tooling half of test wall-clock)
- IMPRV-015 (removes the template-compile slice of the same runs)

## Working

- 2026-08-25 re-validated: `buildTestApp` (`app/test/build-test-app.ts:193-222`) and `openCommerceWorld` (`app/test/commerce-world.ts:48-60`) each run `migrateToLatest` per call. node:test gives each file its own process, so a per-process template caps migrator runs at one per file (~131 files use the fixtures) instead of one per fixture call (~753).
- Baseline (`make test-fast`, Docker, this host): run 1 wall 42.7s / node:test duration_ms 38227; run 2 wall 42.9s / duration_ms 39104. 2022/2022 pass.
- Constraints found in discovery:
  - `app/plugins/health.ts:27` calls `pendingMigrations(db)` against the fixture DB — the template must carry the `kysely_migration` rows (and the lock row), not just DDL.
  - The WAL migration's `PRAGMA journal_mode = WAL` is a no-op on `:memory:` (journal_mode is `memory`); it leaves no `sqlite_master` row, so a DDL dump reproduces what `migrateToLatest` achieves on `:memory:` today.
  - `@fastify/static` only warns when `root` is missing (`checkRootPathForErrors`, index.js:558) — the app still boots and later mkdirs happen in `saveUploadedListingImage` and `drainOutbox` (`mkdir recursive`). So temp dirs can go uncreated by default. Exception: a test that surfaces the log (`overrides.logLevel` or `loggerStream`) would see that warn line pollute `story()`/`text()` assertions in `log-lines.ts`; those apps keep eagerly-created dirs.
  - `migrator.test.ts`, `schema-fidelity.test.ts`, seed tests, CLI tests open their own DBs and call `migrateToLatest` directly — untouched, they keep exercising the real migrator.
  - One caller passes `overrides.db` (`app/plugins/unread-messages.test.ts:33`, a fresh empty query-logging Kysely) — applying the template to a provided empty DB preserves today's semantics.
- Plan: memoize a per-process statement list (DDL from `sqlite_master WHERE sql IS NOT NULL`, plus `kysely_migration`/`kysely_migration_lock` row inserts) built by one real `migrateToLatest` on a throwaway `:memory:` DB; both fixtures apply it to each fresh DB. No disk cache, so in-place migration edits keep taking effect per run. Fastify app memoization deferred pending measurement.
- Implemented: new `app/test/schema-template.ts` exports `applySchemaTemplate(db)` — a module-level memoized promise runs one real `migrateToLatest` against a throwaway `:memory:` DB per test process, dumps `sqlite_master` DDL plus `kysely_migration`/`kysely_migration_lock` row inserts, and every fixture call replays that statement list on its fresh DB via `sql.raw`. Both fixtures now call it. `buildTestApp`'s temp dirs are lazy: `buildIsolatedTestConfig` names a `randomUUID` root without creating it (the app `mkdir`s on demand), creating `uploadsDir` up front only when the test surfaces the log (`overrides.logLevel`/`loggerStream`), which keeps `@fastify/static`'s missing-root warn line out of captured-log assertions. `close` keeps the `rm(force)` — a no-op when nothing wrote.
- Lint: the extra branch pushed `buildTestApp` to complexity 9 (ceiling 8); extracting `buildIsolatedTestConfig` restored it.
- Measured (`make test-fast`, Docker, two runs each): before wall 42.7s / 42.9s (node:test duration_ms 38227 / 39104); after wall 37.5s / 39.7s (duration_ms 33676 / 36470) — ~11-13% off the in-runner duration. 2022/2022 pass, unchanged.
- `make check` green: coverage all files 99.43 lines / 95.88 branches / 99.38 funcs (baseline 95.86 branches; the delta is the new `schema-template.ts` entering the covered set). Gate 95/90 intact.
- Deferred per the ticket's own caution: memoizing a built Fastify app. The DDL template already bounds schema setup to once per process; the remaining per-test cost is app construction, which stays as-is until a measurement says otherwise.

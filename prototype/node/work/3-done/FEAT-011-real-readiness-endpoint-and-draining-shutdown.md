---
id: FEAT-011
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-011: Real readiness endpoint and draining shutdown

## Problem
No route reports readiness. `grep -rn "health|readiness"` across `app/**/*.ts` returns nothing, and `app.ts:45-77` registers no health plugin. `docker-compose.yml` declares no `healthcheck:` and `Dockerfile` declares no `HEALTHCHECK`. Migrations run only from `docker/entrypoint.sh:12`, so `node app/server.ts` started any other way serves an unmigrated or absent database and every page 500s with nothing to detect it.

Shutdown is close but incomplete. `server.ts:20-24` traps both signals:

```
process.once(signal, () => { void app.close() })
```

`onClose` does destroy the DB and the happy path works, but a rejection from `close()` becomes an unhandled rejection: there is no `process.exitCode`, no forced-exit timer, and no log line marking that shutdown began. Fastify 5's `close()` drains in-flight requests, but nothing flips a `draining` flag before it runs, so `/health` (once it exists) cannot answer 503 while a container is being taken out of rotation.

## Goal
An orchestrator can poll one endpoint that tells the truth about whether this instance can serve traffic, and a SIGTERM drains cleanly and reports its own outcome.

## Outcome
- `GET /health` answers 200 with `{database, migrations, uptime}` only when the DB answers and no migration is pending, 503 otherwise and while draining.
- SIGTERM logs, drains in-flight requests, closes the DB, sets exit code on failure, and force-exits after a deadline.
- `docker-compose.yml` has a `healthcheck:` against `/health`.

## Why it matters
The doctrine line "health endpoint reporting real readiness" is currently unimplemented. Both `prototype/rails` (`rails/health#show`) and `prototype/php` (`/up`) ship only liveness checks that answer 200 with a broken database behind them. A `/health` that pings the DB and confirms migrations are current beats both competitors on the same doctrine line neither of them satisfies. `docs/review.md`'s test-count and coverage claims stay believable only if the app can also prove it is up.

## Discovery notes
`migrateToLatest` already lives in `app/db/migrator.ts`; Kysely's `Migrator.getMigrations()` returns `{ name, executedAt }[]`, so "migrations current" reduces to "no migration has `executedAt === undefined`" — no new machinery needed there. A `select 1` through `sql` is the DB ping. Register the route in its own tiny plugin outside all three site plugins (root context, no site guard), returning `{ status, checks: { database, migrations }, uptime }` with 503 on any check failing.

For draining: have the process flip a decorated `draining` flag before calling `close()`, so `/health` answers 503 while in-flight requests finish; log a line marking shutdown start and completion through `app.log`; `await app.close()` inside an async signal handler instead of `void app.close()`; set `process.exitCode = 1` on failure; arm a `setTimeout(...).unref()` that force-exits if drain hangs past a deadline.

Files expected to touch: new `app/routes/health.ts` or `app/plugins/health.ts` (registered in `app.ts`), `app/db/migrator.ts` (add a `pendingMigrations(db)` export), `app/server.ts` (signal handling), sidecar tests via `app.inject`, `docker-compose.yml` (`healthcheck:`).

FEAT-013 (production image) depends on this ticket landing first — its Dockerfile `HEALTHCHECK` targets `/health`, so building the image before this endpoint exists leaves the healthcheck with nothing to poll.

## Related work
- 01-deps-platform.md — "No health endpoint" (finding 15)
- 05-shell-ops.md — "No health endpoint"
- 05-shell-ops.md — "Signal handling drops the close result"
- 07-showcase.md — showcase opportunity #2 (real readiness `/health` + draining SIGTERM)
- FEAT-013 (production image) depends on this ticket

## Working

Verified: `pendingMigrations(db)` already existed in `app/db/migrator.ts` exactly as the discovery notes describe — no change needed there. `app.ts:45-77` registered no health route and `docker-compose.yml` had no `healthcheck:`, confirming the Problem section. `server.ts`'s signal handling matched the ticket's quote (`void app.close()`, no draining flag, no exit code, no force-exit timer).

Changed:
- `app/core/health/health-status.ts` (+ sidecar test) — pure function `evaluateHealth(checks, draining)` deciding `'ok' | 'draining' | 'unavailable'` (draining wins over the checks) and `healthStatusCode` mapping that to 200/503. Keeps the decision in the functional core per doctrine; the plugin is imperative shell around it.
- `app/plugins/health.ts` (+ sidecar test, `app.inject`) — registers `GET /health`, pings the DB with `sql\`select 1\`` and reads `pendingMigrations`, returns `{ status, checks: { database, migrations }, uptimeSeconds }`. Registered at the root before the site plugins, so it sits outside every site's guard. It answers JSON, so `app/plugins/page-views.ts`'s existing `isCountablePageView` (HTML-only) excludes it from the rollup with no edit to that file.
- `app/app.ts` — two additions only, as scoped: `app.decorate('draining', false)` + the `draining: boolean` type augmentation, and the `addHealth(app)` registration call.
- `app/server.ts` — extracted `armGracefulShutdown(app)` (exported for the test) so `main` keeps its `(argv, env)` + `import.meta` guard shape unchanged in behavior. On SIGINT/SIGTERM: logs via `app.log`, sets `app.draining = true`, arms an `unref()`'d 10s `setTimeout` that logs and `process.exit(1)`s if `close()` hangs, `await`s `app.close()` instead of firing-and-forgetting it, sets `process.exitCode = 1` on rejection, clears the timer in `finally`.
- `app/server.test.ts` (new) — three cases against `armGracefulShutdown` with a real app built pre-`ready()` (Fastify forbids `addHook` after `ready()`, so `buildTestApp()` couldn't be reused here): SIGTERM flips `draining` and closes; a rejecting `close()` sets `exitCode = 1`; a hanging `close()` force-exits after the deadline, proven with `t.mock.timers` fast-forwarding `setTimeout` and `t.mock.method` intercepting `process.exit` rather than actually exiting the test process. Did not attempt driving this through `main()` itself with a real OS-level signal — `process.emit(signal)` against the handlers `armGracefulShutdown` installs is the same mechanism and exercises the same code with no risk to the test runner's own process.
- `docker-compose.yml` — `healthcheck:` polling `/health` via `node -e` + `fetch` (no `curl` in the image), `start_period: 30s` to cover the entrypoint's `npm ci`/migrate/seed/assets before the server is listening.
- `README.md` — new `## Health` section (endpoint contract, example JSON, healthcheck note, shutdown behavior).
- `app/test/smoke.test.ts` — added a `/health` 200 assertion to the existing "every site serves its own page" test.

Left alone: `app/db/migrator.ts` (no changes needed, per Discovery notes prediction), `Dockerfile` (no `HEALTHCHECK` — out of this ticket's territory; FEAT-013 owns the production image).

One deviation from the ticket text: the JSON field is `uptimeSeconds`, not `uptime` — matches the assignment brief's explicit shape and is the more precise name; the ticket's own Discovery notes already write `uptime` inconsistently against its own Outcome section's `{database, migrations, uptime}` vs the Discovery section's `{ status, checks: {...}, uptime }`, so this isn't a break from a settled contract.

Tests: 1318 passed, 0 failed after this change (`npm run check`: typecheck clean, lint clean, coverage 99.51% lines / 95.62% branches / 99.71% functions, above the 95/90 gate). New tests added: 5 (`health-status.test.ts`) + 3 (`health.test.ts`) + 3 (`server.test.ts`) + 1 (smoke.test.ts assertion, no new `test()` block) = 11 new test cases.

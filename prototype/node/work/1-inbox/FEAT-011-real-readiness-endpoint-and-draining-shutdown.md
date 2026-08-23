---
id: FEAT-011
type: feature
status: open
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

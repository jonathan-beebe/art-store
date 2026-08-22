---
id: FEAT-001
type: feature
status: open
created: 2026-08-22
---

# FEAT-001: Dockerized Node 24 + Fastify foundation with sidecar node:test and Tailwind

## Problem
`prototype/node/` contains only `docs/architecture.md` and `work/`. There is no application, no container, no test runner, no migrator, and no CSS pipeline. Every other ticket depends on this scaffold and on its conventions (sidecar tests, one plugin per site, three layouts, the debug-alert partial, `buildApp(deps)` as the composition root) being in place.

## Goal
A team member clones the repo, runs one command, and has the app served on `http://localhost:3000` with a green test suite, a type check, a lint gate, and a coverage report, with nothing installed on the host.

## Outcome
- `make up` serves the app at `http://localhost:3000`; `/` renders a storefront placeholder, `/seller` a seller-portal placeholder, and `/admin` an admin placeholder, each in its own layout with Tailwind styles applied and the debug-alert partial present.
- `make test` runs `typecheck`, `lint`, and the `node:test` suite inside the container and is green; `make coverage` prints the node coverage table and fails below the thresholds in `docs/architecture.md`.
- A sidecar `app/core/money.test.ts` beside `app/core/money.ts` passes with no database; an integration sidecar beside each placeholder route builds the app with `buildApp` against an in-memory SQLite and asserts the three pages render through `app.inject`.
- Kysely's migrator runs on start and on `make migrate`; a first migration exists and `make fresh` rebuilds the database from nothing.
- `README.md` documents: prerequisites (Docker only), first run, serving, running tests / coverage / a single test, opening a shell, resetting the database, and the repository layout.

## Why it matters
All later tickets fan out from this scaffold, and the showdown against the PHP and Rails spikes is judged partly on how little tooling the Node stack needs.

## Discovery notes
Read `docs/architecture.md` first; it pins versions and the layout. `prototype/rails/` (`Dockerfile`, `docker/entrypoint.sh`, `Makefile`, `README.md`) is a working reference for the container shape.
- Verified 2026-08-22 in `node:24-bookworm-slim` (Node 24.19, npm 11.17): `node --test --experimental-test-coverage --test-coverage-include='app/**' --test-coverage-lines=90 'app/**/*.test.ts'` runs `.test.ts` sidecars with no flags for type stripping and prints the coverage table. Import specifiers need the `.ts` extension (`allowImportingTsExtensions`, `module: nodenext`, `noEmit`, `erasableSyntaxOnly`, `verbatimModuleSyntax`, `strict`).
- Registry versions that day: fastify 5.12.1, @fastify/view 12.0.0, ejs 6.0.1, @fastify/formbody 9.0.0, @fastify/cookie 11.1.2, @fastify/static 10.1.3, @fastify/multipart 10.1.1, better-sqlite3 13.0.3, kysely 0.29.5, zod 4.4.3, @tailwindcss/cli 4.3.3, typescript 7.0.2 (pin `^5.9` instead — 7.x is the new native compiler and not needed), @types/node (pin `^24`).
- Dockerfile: `node:24-bookworm-slim` plus `python3 make g++` so `better-sqlite3` can compile if no prebuilt binary matches. Bind-mount `./src` to `/var/www/src`; `node_modules` inside the mount. Run as the host uid (`user: "${UID}:${GID}"` in compose, or `-u`) so files are owned by the host user. Entrypoint: `npm ci` when `node_modules` is missing or stale, run migrations, build Tailwind, then exec the command (`node --watch app/server.ts` for development).
- `buildApp({ db, clock, magicLinkDelivery, ... })` returns a Fastify instance; `server.ts` reads `config.ts` and listens. Tests construct the same function with `openDatabase(':memory:')` and run the migrator first. A `clock` is `{ now(): Date }` so tests freeze time.
- Three site plugins (`app/sites/seller`, `shop`, `admin`) each register a placeholder route and their own `views/layout.ejs`; a tiny flash plugin (signed one-request cookie) and the `debug-alert.ejs` partial render `flash.debugMagicLink` in every layout. A `reply.render(view, data)` decorator per site that applies the site layout keeps routes one line.
- eslint flat config with typescript-eslint, `complexity: ['error', 8]`, `max-depth: ['error', 3]`; `npm run lint`. Keep the rule set small.
- First core module, TDD: `app/core/money.ts` — `Cents` (integer), `addCents`, `multiplyCents`, `percentOfCents` (half-cent rounds away from zero), `formatCents` → `$1,234.56` / `-$12.34`, `parseDollars('12.50')` → `1250` and a clear failure on bad input.
- Makefile targets: `up`, `down`, `build`, `assets`, `shell`, `test`, `coverage`, `smoke`, `migrate`, `fresh`, `seed`, `logs`. Keep `.gitignore` for `node_modules`, `storage/*.sqlite3*`, `public/app.css`, `public/uploads`, `coverage`.
- Commit with the ticket id when green, staging only `prototype/node`.

## Related work
- `prototype/rails/work/3-done/FEAT-001-dockerized-rails-foundation.md` — same ticket for the Rails spike; its `## Working` notes list the traps.
- `__local__/retro.md` — process lessons from the two earlier spikes.

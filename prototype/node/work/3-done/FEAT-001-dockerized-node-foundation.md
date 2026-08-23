---
id: FEAT-001
type: feature
status: resolved
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

## Working

### Versions
Node 24.19.0, npm 11.17.0 (`node:24-bookworm-slim`). fastify 5.12.1,
@fastify/view 12.0.0, ejs 6.0.1, @fastify/formbody 9.0.0, @fastify/cookie
11.1.2, @fastify/static 10.1.3, @fastify/multipart 10.1.1, better-sqlite3
13.0.3, kysely 0.29.5, zod 4.4.3. Dev: typescript 5.9.3, @types/node 24.13.3,
eslint 9.39.5, @eslint/js 9.39.5, typescript-eslint 8.67.0, @tailwindcss/cli
4.3.3, @types/ejs 3.1.5, @types/better-sqlite3 9.6.0.

typescript is pinned `^5.9` and @types/node `^24` per the ticket; the registry
serves typescript 7.0.2 and @types/node 26.2.0 as latest. eslint is pinned
`^9.39` to match the architecture doc; latest is 10.9.0 and typescript-eslint
8.67 peers both.

### Decisions
- **`Migrator` and `FileMigrationProvider` import from `kysely/migration`.**
  Kysely 0.29 moved them out of the root export; importing from `kysely`
  fails at runtime with "does not provide an export named".
- **@fastify/view registers once at the root; the layout is chosen per render.**
  The plugin is `fastify-plugin`-wrapped, so three registrations would collide
  on the `view` decorator. The view root is `app/`, so every template is
  addressed from there (`sites/shop/views/home`), and `reply.view(page, data,
  { layout })` — supported for ejs — applies the site layout.
- **`addSiteRender(site, { pages, layout })` is a plain function, not a
  plugin.** Registering it would put `reply.render` in a child context the
  site's route plugins cannot see. Called inside the site plugin, the decorator
  lands in that site's context and its routes inherit it.
- **Flash lives on the reply: `reply.setFlash(flash)` and
  `reply.takeFlash()`.** Fastify 5 refuses reference-type decorator values, so
  `request.flash` as a plain object is out, and only a reply can clear the
  cookie it reads. The render decorator calls `takeFlash()`, so the flash is
  consumed by the page that shows it and a redirect in between keeps it.
- **The first migration enables write-ahead logging and creates no table.**
  FEAT-002 owns `sellers`, `customers`, `admins`, `magic_links`,
  `customer_merges`; FEAT-003 owns the commerce tables and
  `page_view_counts`. The journal mode is stored in the file, so a migration
  is where it belongs; `foreign_keys` is per-connection and set in
  `openDatabase`. `app/db/schema.ts` is an empty `Database` type both tickets
  extend.
- **`app/db/migrator.ts` (the function) is split from `app/db/migrate.ts` (the
  entry).** `FileMigrationProvider` loads every file in `migrations/` as a
  migration, so no sidecar test may live there; the migrator is tested from
  `migrator.test.ts` one directory up.
- **`app/plugins/` holds `flash.ts` and `site-render.ts`** — cross-cutting
  Fastify wiring that is neither a site, an adapter over the database, nor a
  view. Not in the layout in `docs/architecture.md`.
- **`app/clock.ts` holds the `Clock` type with `systemClock` and
  `fixedClock`.** Core takes `now: Date` and never a clock, so `Clock` has no
  reason to sit in `app/core/`.
- **`make fresh` restarts the app container.** A running server holds the
  deleted database file open and would keep writing to the unlinked inode.
- **`parseDollars` rejects `12.5`.** Two decimal places or none, matching
  `Domain::Money.from_dollars` in the Rails spike.

### Deviations from the discovery notes
- No `seed` target and no `app/db/seed.ts`: nothing is seeded yet. FEAT-002
  seeds the two admins and adds the target.
- `npm run fresh` is `node app/db/migrate.ts --fresh` rather than a second
  script, so the database path comes from `config.ts` in both paths.
- @fastify/multipart is installed (the architecture pins it) and not
  registered; the first upload route registers it.
- `@types/ejs` and `@types/better-sqlite3` were added — neither package ships
  types, and `tsc --noEmit` fails without them.
- The eslint config is `src/eslint.config.js`; a `.ts` config would need jiti.
- `buildApp` takes `{ db, clock, config }`. The delivery ports arrive with
  FEAT-002.

### Verified
- Clean first run from an empty tree (no `src/node_modules`, no
  `src/storage/*.sqlite3`, no `src/public/app.css`): `make up` answers on
  <http://localhost:3000> after 15s. `/`, `/seller`, `/admin` all 200
  `text/html`, each linking `/app.css`, which serves 200 `text/css` (10,851
  bytes) with the classes the three layouts use.
- `make test`: 64 tests, 64 pass, 0 fail — `tsc --noEmit`, then eslint, then
  `node --test`. The eslint gate was proven with a throwaway file: complexity
  13 and depth 4 both error.
- `make coverage`: 99.56% lines, 96.30% branches, 97.14% functions, exit 0.
  Raising the line threshold to 100 exits 1, so the gate bites. Only loaded
  files are reported, so `server.ts` and `db/migrate.ts` do not appear.
- `make smoke`: 1 test, pass. `make migrate`, `make fresh` (rebuilds the file
  and restarts the server, which answers again in 1s), `make down`.
- Files written by the container are owned by the host user.
- A first `npm ci` inside `docker compose up` hit a transient
  `EAI_AGAIN` against the registry twice; the same command in
  `docker compose run` and a later `make up` succeeded. The entrypoint's
  `set -e` stops the container, so a retry of `make up` is the fix.

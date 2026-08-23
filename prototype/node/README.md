# Art Store prototype (Node)

A three-sided art marketplace prototype: a seller portal at `/seller`, a
customer storefront at `/`, and an admin site at `/admin`. One Node
deployable, one SQLite file, server-rendered HTML, no client-side JavaScript.

Read [`docs/architecture.md`](docs/architecture.md) before changing code — it
is the spec for layers, naming, routes, and testing conventions.

## Prerequisites

Docker Desktop. Nothing else: Node 24, npm, TypeScript, SQLite, and the
Tailwind CLI live in the `app` container. Nothing is installed on the host,
and every command runs in the container.

## First run

```sh
make up
```

The entrypoint installs dependencies with `npm ci` when `node_modules` is
missing or older than `package-lock.json`, runs `node app/db/migrate.ts`,
builds the Tailwind stylesheet with `npm run assets`, then starts the server
with `node --watch app/server.ts`. The first run takes a few minutes while
the image builds and better-sqlite3 resolves; later runs take seconds.

Then open:

- Storefront — <http://localhost:4000/>
- Seller portal — <http://localhost:4000/seller>
- Admin site — <http://localhost:4000/admin>

`make down` stops the stack. `make logs` follows the server output.

## Configuration

`src/app/config.ts` parses these from the environment; `docker-compose.yml`
sets `HOST` and `PORT` for the container.

| Variable | Default |
| --- | --- |
| `HOST` | `0.0.0.0` |
| `PORT` | `4000` |
| `DATABASE_FILE` | `storage/development.sqlite3` |
| `COOKIE_SECRET` | a development default (minimum 16 characters) |
| `LOG_LEVEL` | `info` |

## Commands

Every target is a thin `docker compose` wrapper, so either form works.

| Make | Docker Compose |
| --- | --- |
| `make up` | `docker compose up -d` |
| `make down` | `docker compose down` |
| `make build` | `docker compose build` |
| `make assets` | `docker compose run --rm app npm run assets` |
| `make shell` | `docker compose run --rm app bash` |
| `make test` | `docker compose run --rm app npm run check` |
| `make smoke` | `docker compose run --rm app node --test app/test/smoke.test.ts` |
| `make coverage` | `docker compose run --rm app npm run coverage` |
| `make migrate` | `docker compose run --rm app npm run migrate` |
| `make fresh` | `docker compose run --rm app npm run fresh`, then `docker compose restart app` |
| `make logs` | `docker compose logs -f` |

The Makefile exports the host `UID` and `GID`, which `docker-compose.yml`
reads to run the container as that user, so files the container writes into
`src/` belong to the host user. A raw `docker compose run` without that
export needs `--user "$(id -u):$(id -g)"`.

## Running one test

```sh
docker compose run --rm app node --test app/core/money.test.ts
```

Filter by test name with `--test-name-pattern`:

```sh
docker compose run --rm app node --test --test-name-pattern="percent" app/core/money.test.ts
```

## Testing

Tests are sidecars: `foo.ts` gets `foo.test.ts` beside it. `node:test` and
`node:assert/strict` — no test framework is installed. `make test` runs
`npm run check`: `tsc --noEmit`, then eslint (`complexity` max 8, `max-depth`
max 3), then `node --test 'app/**/*.test.ts'`. `make coverage` prints
Node's own coverage table and fails under 90% lines / 80% branches.
`make smoke` runs `app/test/smoke.test.ts`, which walks every site.

Core tests (`app/core/**`) import only the file under test. Route tests build
the whole app over an in-memory SQLite with `buildTestApp()` from
`app/test/build-test-app.ts` and drive it with `app.inject`.

## Database

SQLite at `src/storage/development.sqlite3`, created on first run.
Write-ahead logging is on and foreign keys are enforced per connection.
Kysely reads it through `SqliteDialect` with `CamelCasePlugin`, so
snake_case columns read as camelCase in TypeScript (`price_cents` →
`priceCents`).

```sh
make migrate    # apply pending migrations
make fresh      # delete the file, rebuild it from the migrations, restart the server
```

Tests run against `:memory:`.

## Adding a migration

Create `src/app/db/migrations/<YYYYMMDDHHMMSS>-<kebab-name>.ts` exporting
`up(db: Kysely<unknown>)` and `down(db: Kysely<unknown>)`; add the table's
row type to `src/app/db/schema.ts`; run `make migrate`. Every file in that
directory is loaded as a migration, so no sidecar test may live there — test
the behavior from the module that uses the table.

## Styling

Tailwind v4 through `@tailwindcss/cli` in the container. Source is
`src/app/assets/app.css` (`@import "tailwindcss"` plus an `@source` pointing
at `app/`); output is `src/public/app.css`, which is not committed and is
rebuilt on every container start. To rebuild without restarting:

```sh
make assets
```

Layouts link it as `/app.css`, served by `@fastify/static` from
`src/public/`. There is no JavaScript bundle and no `<script>` tag in any
view.

## No build step

Node 24 strips TypeScript types natively: `node app/server.ts` runs the
source directly. No tsx, no bundler, no dist directory. `tsc` runs for type
checking only (`--noEmit`).

Two consequences follow: import specifiers carry the `.ts` extension they
have on disk, and `erasableSyntaxOnly` is on, so no `enum`, no parameter
properties, no namespaces — use `as const` string unions instead.

## Layout

```
prototype/node/
  README.md            this file
  Dockerfile            node:24-bookworm-slim + build tools for better-sqlite3
  docker-compose.yml    one service: app
  docker/entrypoint.sh  install, migrate, build assets, then the container command
  Makefile              host-side wrappers over docker compose
  docs/architecture.md  the spec
  work/                 tickets and journal
  src/                   the Node project
    package.json, package-lock.json, tsconfig.json, eslint.config.js
    app/
      server.ts          entry: loads config, opens the database, listens
      app.ts              buildApp(deps): composition root
      config.ts           env -> typed config
      clock.ts             systemClock and fixedClock
      core/                functional core, sidecar tests (money.ts today)
      db/                  database.ts, migrator.ts, migrate.ts, schema.ts, migrations/
      plugins/             flash.ts, site-render.ts
      sites/               shop/, seller/, admin/ — each a plugin with routes/ and views/
      views/partials/      debug-alert.ejs, flash.ejs
      test/                build-test-app.ts, smoke.test.ts
      assets/app.css       Tailwind source
    public/                app.css (built, not committed)
    storage/               development.sqlite3 (not committed)
```

`app/actions/`, `app/delivery/`, `app/cli/`, and `app/sites/auth/` do not
exist yet; the tickets that need them create them.

## What exists today

Three sites — shop at `/`, seller at `/seller`, admin at `/admin` — each with
a placeholder home page in its own layout. The flash cookie
(`app/plugins/flash.ts`) and the shared `debug-alert` partial. `app/core/money.ts`.
The migrator with one migration, enabling write-ahead logging. The
test/typecheck/lint/coverage gates behind `make test` and `make coverage`.

Not yet: identity (magic links, sessions), the commerce domain (listings,
orders, fulfillments, payouts), messaging, seeds, and email.

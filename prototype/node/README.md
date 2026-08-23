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
| `MAGIC_LINK_DELIVERY` | `flash` (`mail` throws `NotImplementedError`) |

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
| `make fresh` | `docker compose run --rm app npm run fresh`, then seed, then `docker compose restart app` |
| `make seed` | `docker compose run --rm app npm run seed` |
| `make payouts` | `docker compose run --rm app npm run payouts` |
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
make fresh      # delete the file, rebuild it, seed the admins, restart the server
make seed       # add the platform admins; running it twice adds nobody
```

Tests run against `:memory:`.

## Seeded accounts

`make seed` adds the two platform admins, then a demo catalog, customers, and
order history — all through the same actions the sites call, with a frozen
clock so the dates read the same on any day. The demo half refuses to run
twice: it does nothing once a seller row exists, so a second `make seed` (or
the one `make fresh` runs for you) only confirms the admins are still there.
Sign in as any address below from that site's `/login` page — the debug alert
prints the magic link.

**Admins** — seeded only, never created by signing in.

| Email | Name |
| --- | --- |
| `jonathan-beebe@outlook.com` | Jonathan Beebe |
| `annaschmunk@pm.me` | Anna Schmunk |

**Sellers** — all four verified, each with a shop and part of a ~30-listing
catalog across six media (painting, print, ceramic, textile, sculpture,
photography): 24 `for_sale` (one, "Night Freight", carries an admin's
temporary removal and is off the storefront despite its status), 3 `draft`,
2 `sold`.

| Email | Name | Shop |
| --- | --- | --- |
| `maya@example.com` | Maya Reyes | Terra & Glaze Ceramics |
| `noah@example.com` | Noah Chen | North Light Editions |
| `priya@example.com` | Priya Anand | Priya Anand Textile Studio |
| `leo@example.com` | Leo Martins | Leo Martins Photography |

**Customers**

| Email | State |
| --- | --- |
| `casey@example.com` | Verified. 3 favorites, 6 viewed listings, an in-progress cart, and orders in `paid`, `shipped`, and `delivered` states. |
| `jordan@example.com` | Verified, blocked by an admin (`customer_blocks`). |
| _(3 anonymous)_ | No address given; each has view history on a few listings. |

## Weekly payouts

Escrow released by a confirmed delivery is settled a week at a time.

```sh
make payouts                    # the Monday-to-Sunday week that just ended
make payouts AS_OF=2026-08-24   # the week before that date
```

The `paid_out` ledger entry a run writes is dated at the close of the period it
settles, so running the same period again pays nothing.

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

`app/actions/` holds the verbs over `{ db, clock }` and `app/cli/` the commands
(`run-payouts.ts`); neither is in the tree above.

## What exists today

Three sites — shop at `/`, seller at `/seller`, admin at `/admin` — each in
its own layout. The flash cookie (`app/plugins/flash.ts`) and the shared
`debug-alert` partial. `app/core/money.ts`. The migrator. The
test/typecheck/lint/coverage gates behind `make test` and `make coverage`.

Passwordless sign-in for all three: `/seller/login`, `/login`, and
`/admin/login` issue a magic link that `/auth/magic/:token` spends, and each
site has `/account` and sign-out. Every storefront request carries a customer,
anonymous until an address is verified. Admins are seeded (`make seed`), never
created by signing in.

Not yet: messaging and real email.

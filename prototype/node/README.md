# Art Store prototype (Node)

A three-sided art marketplace prototype: a seller portal at `/seller`, a
customer storefront at `/`, an admin site at `/admin`, and a messaging centre
that spans all three. One Node deployable, one SQLite file, server-rendered
HTML, no client-side JavaScript.

Read [`docs/architecture.md`](docs/architecture.md) before changing code — it
is the spec for layers, naming, routes, and testing conventions.
[`docs/review.md`](docs/review.md) maps every requirement in the brief to the
route and test that prove it, and lists what is missing.

## Prerequisites

Docker Desktop. Nothing else: Node 24 (SQLite included), npm, TypeScript,
and the Tailwind CLI live in the `app` container. Nothing is installed on the host,
and every command runs in the container.

## First run

```sh
make build
make up
```

The entrypoint installs dependencies with `npm ci` when `node_modules` is
missing or older than `package-lock.json`, runs `node app/db/migrate.ts`,
seeds the platform admins and demo catalog with `node app/db/seed.ts`, builds
the Tailwind stylesheet with `npm run assets`, then starts the server with
`node --watch app/server.ts`.

Measured from an empty tree — no `src/node_modules`, no SQLite file, no
`src/public/app.css`: `make build` took 14 seconds and `make up` another 13,
of which `npm ci` was 9. Add the one-time pull of `node:24-bookworm-slim` on a
machine that has never seen it. Later runs take seconds, because
`src/node_modules` lives in the bind mount and survives `make down`.

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

## Seeded accounts

`make seed` (and the entrypoint on every start) adds the two platform admins,
then a demo catalog, customers, and order history — all through the same
actions the sites call, with a frozen clock so the dates read the same on any
day. The demo half refuses to run twice: it does nothing once a seller row
exists, so a second `make seed` (or the one `make fresh` runs for you) only
confirms the admins are still there. Sign in as any address below from that
site's `/login` page — the debug alert prints the magic link.

**Admins** — seeded only, never created by signing in.

| Email | Name |
| --- | --- |
| `jonathan-beebe@outlook.com` | Jonathan Beebe |
| `annaschmunk@pm.me` | Anna Schmunk |

**Sellers** — all four verified, each with a shop and part of a 29-listing
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
| `casey@example.com` (Casey Whitfield) | Verified. 3 favorites, 6 viewed listings, a standing cart with 2 items, and 3 single-item orders in `paid` (awaiting shipment), `shipped`, and `delivered` states — the delivered one's escrow is released and paid out in the weekly payout run. |
| `jordan@example.com` | Verified, blocked by an admin (`customer_blocks`, reason "Repeated chargebacks reported by two sellers."). |
| _(3 anonymous)_ | No address given; each has view history on a few listings. |

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
| `make docs-check` | `./docker/docs-check.sh` |
| `make migrate` | `docker compose run --rm app npm run migrate` |
| `make fresh` | `docker compose run --rm app npm run fresh`, then seed, then `docker compose restart app` |
| `make seed` | `docker compose run --rm app npm run seed` |
| `make payouts` | `docker compose run --rm app npm run payouts -- $(if $(AS_OF),--as-of=$(AS_OF))` |
| `make logs` | `docker compose logs -f` |

The Makefile exports the host `UID` and `GID`, which `docker-compose.yml`
reads to run the container as that user, so files the container writes into
`src/` belong to the host user. A raw `docker compose run` without that
export needs `--user "$(id -u):$(id -g)"`.

`make docs-check` renders every Mermaid block under `docs/` through
`minlag/mermaid-cli` in Docker and fails on any diagram that does not parse.

## Tests

Tests are sidecars: `foo.ts` gets `foo.test.ts` beside it. `node:test` and
`node:assert/strict` — no test framework is installed. `make test` runs
`npm run check`: `tsc --noEmit`, then eslint (`complexity` max 8, `max-depth`
max 3), then the coverage-gated suite (see Coverage below). `npm test` on its
own runs the suite without the coverage gate, for a fast local loop.

Core tests (`app/core/**`) import only the file under test — no database, no
doubles. Route tests build the whole app over an in-memory SQLite with
`buildTestApp()` (`app/test/build-test-app.ts`) and drive it with
`app.inject`.

Run one file:

```sh
docker compose run --rm app node --test app/core/money.test.ts
```

Filter by test name with `--test-name-pattern`:

```sh
docker compose run --rm app node --test --test-name-pattern="percent" app/core/money.test.ts
```

## Coverage

```sh
make coverage
```

Runs `node --test --experimental-test-coverage --test-coverage-include='app/**' --test-coverage-exclude='app/**/*.test.ts' --test-coverage-lines=95 --test-coverage-branches=90 'app/**/*.test.ts'`,
printing Node's own coverage table and failing under 95% lines / 90% branches.

The suite stands at 1,161 tests and 99.42% lines / 95.23% branches / 98.85%
functions. What is left uncovered is migration `down()` bodies and a handful of
defensive branches.

`node:test` itself is stable. `--experimental-test-coverage` and the
`--test-coverage-lines`/`--test-coverage-branches` threshold flags it enables
are still Node's own Stability 1 (Experimental) — the platform's own test
runner gates the build, but that gate is built on an experimental flag, not a
stable one.

## CI

[`.github/workflows/node.yml`](../../.github/workflows/node.yml) runs on
every push to `main` and every pull request touching `prototype/node/**`: it
installs on Node 24, builds the Tailwind stylesheet the smoke test serves,
then runs `npm run check` — typecheck, lint, and the coverage-gated suite —
and uploads `coverage/lcov.info` as a build artifact.

## Smoke

```sh
make smoke
```

`app/test/smoke.test.ts` walks the whole product over HTTP, and `make test`
includes it: a seller signs in by magic link and lists a piece; a fresh
anonymous visitor views, favorites, and carts it, checks out as a guest,
verifies the address from the debug alert, is declined once, then pays with
4242; the seller reads the "Item sold" notification and ships; the customer
confirms delivery; an admin runs the weekly payout and the seller's earnings
page shows the net; a customer asks a question and the seller publishes the
answer as an FAQ; an admin removes a listing and it leaves the storefront; an
admin blocks a customer and checkout refuses. Time is frozen so the payout
period reads the same whatever day it runs.

## Database

SQLite at `src/storage/development.sqlite3`, created on first run. The
engine is `node:sqlite`, the SQLite built into the Node 24 runtime, so the
project has no compiled dependency and the image carries no compiler. The
first migration turns on write-ahead logging.

Kysely reaches it through `app/db/node-sqlite-dialect.ts`, a dialect this
project owns: one connection, `PRAGMA foreign_keys = ON` and
`PRAGMA busy_timeout = 5000` set explicitly on open, and transactions that
begin `IMMEDIATE` so a read-then-write transaction cannot lose its snapshot
to another process under WAL. `CamelCasePlugin` maps snake_case columns to
camelCase in TypeScript (`price_cents` → `priceCents`).

`node:sqlite` is a release candidate, not stable, and Node before 24.15
prints an `ExperimentalWarning` on import — the npm scripts and the image
pass `--disable-warning=ExperimentalWarning` to silence it. The API may
still change between Node minors. Reverting is one file: restore
`better-sqlite3` and Kysely's own `SqliteDialect` in `app/db/database.ts`
and delete the dialect; nothing else imports `node:sqlite`.

```sh
make migrate    # apply pending migrations
make fresh      # delete the file, rebuild it, seed the admins and demo data, restart the server
make seed       # add the platform admins and demo data; running it twice adds nobody new
```

Tests run against `:memory:`.

### Adding a migration

Create `src/app/db/migrations/<YYYYMMDDHHMMSS>-<kebab-name>.ts` exporting
`up(db: Kysely<unknown>)` and `down(db: Kysely<unknown>)`; add the table's
row type to `src/app/db/schema.ts` (or `commerce-schema.ts`); run
`make migrate`. Every file in that directory is loaded as a migration, so no
sidecar test may live there — test the behavior from the module that uses the
table.

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
view (zero across all 57 `app/**/*.ejs` templates).

## Magic links and the email hooks

Passwordless for all three actor types — sellers, customers, and admins. A
link lasts 15 minutes and works once, enforced by the update that consumes
it rather than by the read, so two requests arriving together cannot both
spend it. The first link for an address creates the seller row; there is no
separate seller sign-up. Admin rows are seeded only (the two accounts above)
— `adminSite` never sends a link to an unseeded address. Every storefront
visitor gets a `customers` row before giving an address; verifying an
address either claims that row or folds it into the account that already
holds the address (carts sum quantities clamped to stock, favorites
de-duplicate).

Two hooks are where real email would go, and neither is implemented today:

- `mailMagicLinkDelivery` (`app/delivery/mail-magic-link-delivery.ts`,
  selected by `MAGIC_LINK_DELIVERY=mail`) throws `NotImplementedError` —
  setting that env var breaks sign-in outright.
- `NotificationDelivery` (`app/core/notifications/notification-delivery.ts`)
  is a port type with no implementation anywhere in the tree.
  `ActionContext.notificationDelivery` is optional and stays `undefined` in
  the running app, so the prototype's notifications are the `notifications`
  rows themselves, read from `/seller/notifications` and the storefront's
  `/account`. `notify` calls the port only when one is supplied.

## Paying

`decideCard(cardNumber)` (`app/core/payments/fake-card.ts`) is the fake card
processor:

| Number | Result |
| --- | --- |
| `4242 4242 4242 4242` | approved |
| `4000 0000 0000 0002` | declined — generic decline |
| `4000 0000 0000 9995` | declined — insufficient funds |
| anything else | declined — invalid card number |

Every non-digit is stripped, so spaces and dashes are ignored. Only the last
four digits are ever stored, one `payments` row per attempt. A decline
returns the stock to the listing and leaves a retry form on the order page.

## Admin

Sign in at `/admin/login` as either seeded admin; the debug alert prints the
magic link. The console (everything below `/admin`) sits behind
`requireAdmin`.

| Page | Route |
| --- | --- |
| Dashboard | `GET /admin` |
| Sellers | `GET /admin/sellers`, `GET /admin/sellers/:id` |
| Customers | `GET /admin/customers?standing=` (`all` \| `verified` \| `anonymous` \| `blocked`) |
| Listings | `GET /admin/listings?status=&seller=&removed=` (`removed` is `any` \| `removed` \| `visible`) |
| Orders | `GET /admin/orders?status=&customer=` |
| Fulfillments | `GET /admin/fulfillments?status=&seller=` |
| Accounting | `GET /admin/accounting` |
| Payouts | `GET /admin/payouts?seller=`, `POST /admin/payouts` |
| Ledger | `GET /admin/ledger?seller=&type=` |
| Stats | `GET /admin/stats` |
| Messages | `GET /admin/messages` |

Two moderation tools, both catching a `TransitionError` refusal into a flash:

- **Listing removal** — `POST /admin/listings/:id/removals` (`kind`:
  `temporary` or `permanent`, plus `reason`) and
  `POST /admin/listings/:id/removals/lift`. A permanent removal cannot be
  lifted. Either kind takes the listing off the storefront whatever its own
  status; the seller sees the removal and reason in the portal.
- **Customer block** — `POST /admin/customers/:id/blocks` (`reason`) and
  `POST /admin/customers/:id/blocks/lift`. A blocked customer can still
  browse but is refused at cart, checkout, payment, and messages.

At most one active removal per listing and one active block per customer.

## Messaging and FAQ

One model serves every pairing: a `conversations` row names its `kind` and
its participants, `messages` rows hang off it.

| `kind` | Participants | Opened from |
| --- | --- | --- |
| `admin_seller` | admin ↔ seller | `/seller/support`, or `POST /admin/sellers/:id/messages` |
| `admin_customer` | admin ↔ customer | `/support`, or `POST /admin/customers/:id/messages` |
| `fulfillment` | seller ↔ customer | `POST /seller/orders/:id/messages`, or `POST /orders/:id/fulfillments/:fulfillmentId/messages` |
| `listing_question` | customer ↔ seller | `POST /art/:slug/questions` |

Each site has its own inbox: `/seller/messages`, `/messages`,
`/admin/messages`, listing conversations newest-first with an unread badge in
the layout. Entry points on existing pages: "Ask the seller a question" on
`/art/:slug`, "Message the customer" on a fulfillment, "Message the seller"
on a storefront order, "Contact support" on `/account`, "Message seller" /
"Message customer" on the admin's seller and customer pages.

A seller answering a `listing_question` can publish the question and answer
to the listing page as an FAQ entry, and can edit or unpublish it afterward —
a published row is deleted on unpublish, so there is no draft state.
Anonymous customers can ask a listing question with no address; the
conversation re-points on merge like any other customer-owned row.

## Weekly payouts

Escrow released by a confirmed delivery is settled a week at a time, Monday
through Sunday.

```sh
make payouts                    # the Monday-to-Sunday week that just ended
make payouts AS_OF=2026-08-24   # the week before that date
```

The `paid_out` ledger entry a run writes is dated at the close of the period
it settles, so running the same period again pays nothing. The admin site
also exposes the run at `POST /admin/payouts`; the seller portal has no
payout control.

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
  Dockerfile            node:24-bookworm-slim, no compiler toolchain
  docker-compose.yml    one service: app
  docker/
    entrypoint.sh        install, migrate, seed, build assets, then the container command
    docs-check.sh        renders every Mermaid block under docs/ through mermaid-cli
  Makefile               host-side wrappers over docker compose
  docs/                  architecture.md (the spec) + feature docs + review.md
    README.md, architecture.md, identity.md, orders.md, escrow.md,
    messaging.md, admin.md, data-model.md, ontology.md, review.md
  work/                  tickets and journal
    1-inbox/, 2-doing/, 3-done/, journal.md
  src/                   the Node project
    package.json, package-lock.json, tsconfig.json, eslint.config.js
    app/
      server.ts          entry: loads config, opens the database, listens
      app.ts              buildApp(deps): composition root
      config.ts           env -> typed config
      clock.ts             systemClock and fixedClock
      not-implemented-error.ts
      core/                functional core, sidecar tests, no I/O:
                           analytics/, auth/, cart/, customers/, escrow/,
                           listings/, messaging/, moderation/, notifications/,
                           orders/, payments/, reports/, shop/, money.ts,
                           transition-error.ts
      actions/             verbs over ActionContext, one folder per concept:
                           analytics/, auth/, carts/, customers/, escrow/,
                           favorites/, fulfillments/, listings/, messaging/,
                           moderation/, notifications/, orders/,
                           action-context.ts, transaction.ts
      db/                  database.ts, node-sqlite-dialect.ts, migrator.ts,
                           migrate.ts, schema.ts,
                           commerce-schema.ts, timestamp.ts, migrations/,
                           seed.ts + seed-admins/catalog/sellers/customers/
                           order-history/messaging/page-views/demo-data.ts
      delivery/            MagicLinkDelivery port + flash and mail implementations
      plugins/             flash.ts, form-body.ts, identity.ts, page-views.ts,
                           site-render.ts, unread-messages.ts
      sites/               shop/, seller/, admin/, auth/ — each a plugin with
                           routes/, views/, and (except auth) queries/
      views/partials/      debug-alert.ejs, flash.ejs
      cli/                 run-payouts.ts, parse-as-of.ts
      test/                build-test-app.ts, commerce-world.ts, smoke.test.ts
      assets/app.css       Tailwind source
    public/                app.css (built, not committed), uploads/
    storage/               development.sqlite3 (not committed)
```

## Known gaps

Email is unimplemented on both delivery ports. Seeded listings show
procedurally generated SVG placeholders rather than photographs. Shipment
tracking is two free-text fields (carrier, tracking number) with no carrier
integration — the customer confirms their own delivery. See
[`docs/review.md`](docs/review.md) for the full numbered list of gaps and
suggested next steps.

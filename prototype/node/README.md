# Art Store prototype (Node)

A three-sided art marketplace prototype: a seller portal at `/seller`, a
customer storefront at `/`, an admin site at `/admin`, and a messaging centre
that spans all three. One Node deployable, one SQLite file, server-rendered
HTML. Every flow works with JavaScript off; the only script on any page is 21
dependency-free lines that move the unread-message badge over a server-sent
event stream.

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
`src/public/app.css`: `make build` and `make up` together took **29 seconds**
to the healthcheck reporting `healthy`, inside which `npm ci`
installed 230 packages (of the 260 in the lockfile; the rest are
platform-specific optional binaries npm skips), thirteen migrations applied from nothing, the seed wrote
2 admins, 4 sellers, 29 listings, 5 customers, 3 orders, 98 page-view rows,
4 conversations, 11 messages and 1 listing FAQ, and Tailwind built
`public/app.css`. Add the one-time pull of `node:24.19.0-bookworm-slim` on a
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

| Variable                        | Default                       | What it decides                                                                  |
| ------------------------------- | ----------------------------- | -------------------------------------------------------------------------------- |
| `NODE_ENV`                      | `development`                 | One of `development`, `test`, `production`. Production is the strict boot: the   |
|                                 |                               | two rules below, plus `Secure` cookies.                                          |
| `HOST`                          | `0.0.0.0`                     | The interface the server binds.                                                  |
| `PORT`                          | `4000`                        | The port it listens on.                                                          |
| `DATABASE_FILE`                 | `storage/development.sqlite3` | The SQLite file. Tests use `:memory:`.                                           |
| `COOKIE_SECRET`                 | a development default         | Signs the flash and identity cookies; minimum 16 characters. **Required** under  |
|                                 |                               | `NODE_ENV=production`.                                                           |
| `PUBLIC_URL`                    | unset                         | The origin every magic link is built from. Unset, a link carries the request's   |
|                                 |                               | own origin — which is the `Host` header.                                         |
| `TRUSTED_PROXIES`               | unset                         | Comma-separated proxy addresses/CIDRs. Set, `request.ip` (what every rate        |
|                                 |                               | limit's client-ip key reads), `request.protocol`, and `request.hostname` trust   |
|                                 |                               | `X-Forwarded-*` headers only past those hops instead of the raw socket.          |
| `MAGIC_LINK_DELIVERY`           | `flash`                       | `flash` prints the link into the page (development only — production refuses     |
|                                 |                               | it); `outbox` queues it for the transactional outbox.                            |
| `UPLOADS_DIR`                   | `public/uploads`              | Where listing images land, served under `/uploads/`.                             |
| `OUTBOX_DIR`                    | `storage/outbox`              | Where draining the outbox writes its `.eml` files.                               |
| `STALE_ORDER_HOURS`             | `24`                          | How long an order left unverified holds its stock before `make sweep` cancels    |
|                                 |                               | it.                                                                              |
| `LOG_LEVEL`                     | `info`                        | `fatal`, `error`, `warn`, `info`, `debug`, `trace`, or `silent`. `debug` adds    |
|                                 |                               | the `listing.view` and `ledger.write` lines.                                     |
| `RATE_LIMIT_MAGIC_LINK_REQUEST` | `5/15m`                       | Sign-in's `POST /login` on every site, and guest checkout's implicit link. Keyed |
|                                 |                               | by lowercased email and, separately, client ip.                                  |
| `RATE_LIMIT_MAGIC_LINK_CONSUME` | `20/15m`                      | `GET /auth/magic/:token`. Keyed by client ip.                                    |
| `RATE_LIMIT_MESSAGE_POST`       | `30/1h`                       | Every message POST. Keyed by actor id.                                           |
| `RATE_LIMIT_CONVERSATION_OPEN`  | `10/1h`                       | The listing question box, support, and fulfillment thread opens. Keyed by actor  |
|                                 |                               | id.                                                                              |
| `RATE_LIMIT_CHECKOUT`           | `10/1h`                       | `POST /checkout`. Keyed by customer id.                                          |
| `RATE_LIMIT_PAYMENT_ATTEMPT`    | `5/15m`                       | `POST /orders/:id/pay`. Keyed by order id.                                       |
| `RATE_LIMIT_LISTING_WRITE`      | `60/1h`                       | Listing create and update. Keyed by seller id.                                   |

Every `RATE_LIMIT_*` value is `<count>/<window>` (`<n>s`, `<n>m`, or `<n>h`) or
`off`. A malformed value refuses to boot, the same as an unsafe production
setting. A trip answers `429` with a `Retry-After` header, the site's own page
("Too many requests — try again in N minutes."), and one `rate_limit.exceed`
log line; the write the limit guards does not happen. Counters live in the
`rate_limit_windows` table, so they survive a restart. See
`../../docs/alignment.md` §3.

Cookies carry `Secure` when `NODE_ENV=production` or `PUBLIC_URL` is an
`https:` origin — there is no separate switch for it.

A production boot refuses to start rather than run unsafely: without
`COOKIE_SECRET` (a shared default makes an admin cookie forgeable), and with
`MAGIC_LINK_DELIVERY=flash` (it prints sign-in links into the page that asked
for one). Both throw from `loadConfig` with the reason.

## Logs

Every line is one JSON object on stdout, in every environment, in the payload
all three prototypes share (`../../docs/alignment.md` §2). A line carries `ts`,
`level`, `event`, `phase`, and `msg` always, plus `request_id`, `session_id`,
`actor_type`, `actor_id`, `txn_id`, `data`, `error`, and `duration_ms` where
they apply.

```json
{"level":"info","ts":"2026-08-24T04:03:19.982Z","pid":1,"hostname":"012798ac8c7d","request_id":"208baa17-25b5-4ebe-b605-fbac29a5a9bd","session_id":"ses_01M0RYZQQQTPVW5D8MC9KWPS06","actor_type":"customer","actor_id":"cus_01M0RYZQQXD6A1SF40F6TZFFMP","event":"http.request","phase":"will","data":{"method":"GET","path":"/art/x"},"msg":"GET /art/x"}
```

Every write tells a story: `will` before it, then `did`, `refused` (the domain
said no, at `info`), or `failed` (an exception nobody expected, at `error`).
Reading one `txn_id` back gives the whole unit of work; reading one `request_id`
gives the whole request. A browser is named by the `sid` cookie (`ses_<ulid>`,
one year, unchanged by sign-in and sign-out); a caller may name its own request
with `X-Request-Id`, which is echoed back when it matches
`^[A-Za-z0-9_-]{1,64}$`. Cookie values, magic-link tokens, card numbers, and
email addresses never appear.

[`docs/architecture.md`](docs/architecture.md#the-log) has the field table, the
event vocabulary, and how each field reaches a line.

## Security headers

`app/plugins/security-headers.ts` adds one `onSend` hook at the root, so a
page, the JSON health check, an uploaded file, and a 404 all answer with the
same set:

| Header                    | Value                                                                                                                  |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `X-Content-Type-Options`  | `nosniff`                                                                                                              |
| `X-Frame-Options`         | `DENY`                                                                                                                 |
| `Referrer-Policy`         | `strict-origin-when-cross-origin`                                                                                      |
| `Content-Security-Policy` | `default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'` |

`data:` is in `img-src` for the generated SVG placeholder a listing with no
photograph renders inline. No page has a script tag or an inline style, so
nothing else needs a relaxation.

## CSRF

Every state-changing request (`POST`, `PUT`, `PATCH`, `DELETE`) across all
three sites carries a double-submit token, verified in one `preValidation`
hook (`app/plugins/csrf.ts`) ahead of the route's own zod schema — checking
any later would let a request through whose schema forgot the field, since
`submittedForm` strips unknown fields by then. The token is
`HMAC-SHA256(COOKIE_SECRET, sid)`, so it rides the browser's existing session
cookie rather than a second one; a shared partial (`_csrf_token`) renders it
into every `<form method="post">`. A missing, foreign, or stale token answers
the requesting site's own 403 page. The guard is registered inside each site
rather than once at the root, after that site's own body parser, so the
seller portal's `@fastify/multipart` upload has already populated
`request.body` by the time the check runs.

## Health

`GET /health` answers 200 only when the database responds and no migration
is pending; 503 with `status: "unavailable"` otherwise, and 503 with
`status: "draining"` once a SIGINT or SIGTERM has told the instance to stop
taking traffic.

```json
{
  "status": "ok",
  "checks": { "database": "ok", "migrations": "current" },
  "uptimeSeconds": 42
}
```

`docker-compose.yml`'s `healthcheck:` polls it with `node -e` and `fetch` —
the image has no `curl`. A SIGTERM (`docker compose stop`, or an
orchestrator taking the container out of rotation) logs, flips `/health` to
`draining`, waits for in-flight requests to finish, closes the database, and
force-exits after 10 seconds if `close()` hangs.

## Deployment

`Dockerfile` has three targets. `dev` is today's bind-mount workflow — `make
build` and `make up` build this target, unchanged. `build` installs every
dependency once and compiles the Tailwind stylesheet at image build time
rather than at container start. `runtime` is the production image:
`npm ci --omit=dev`, `app/` and the built `public/app.css` copied in (no
bind mount), `NODE_ENV=production`, `USER node`, and a `HEALTHCHECK` against
`/health`.

Build it:

```sh
make image
```

Equivalent to `docker build --target runtime -t art-store-node .` from
`prototype/node`. On `node:24.19.0-bookworm-slim` with 87 production
packages (dev-only packages — eslint, typescript, typescript-eslint, the
Tailwind CLI — never enter the image), the result is 289MB.

The image has no entrypoint and does not seed. Migrate explicitly before the
first run:

```sh
docker run --rm \
  -v art-store-storage:/var/www/src/storage \
  -e COOKIE_SECRET=<32+ random bytes> \
  art-store-node node app/db/migrate.ts
```

Then run it:

```sh
make run-image
```

Equivalent to
`docker run --rm -p 4100:4000 art-store-node` (port 4100, so it never
collides with `make up`'s 4000). Mount both declared volumes to persist
state across restarts — `storage` (the SQLite file, `DATABASE_FILE` defaults
to `storage/production.sqlite3` inside the image) and `public/uploads`
(`UPLOADS_DIR`, listing images) — or every restart starts from an empty
database and an empty upload directory:

```sh
docker run --rm -p 4100:4000 \
  -v art-store-storage:/var/www/src/storage \
  -v art-store-uploads:/var/www/src/public/uploads \
  -e COOKIE_SECRET=<32+ random bytes> \
  art-store-node
```

See Configuration above for every variable `app/config.ts` reads;
`COOKIE_SECRET` is the one worth setting explicitly outside development.

## Seeded accounts

`make seed` (and the entrypoint on every start) adds the two platform admins,
then a demo catalog, customers, and order history — all through the same
actions the sites call, with a frozen clock so the dates read the same on any
day. The demo half refuses to run twice: it does nothing once a seller row
exists, so a second `make seed` (or the one `make fresh` runs for you) only
confirms the admins are still there. Sign in as any address below from that
site's `/login` page: with the default `MAGIC_LINK_DELIVERY=flash` the debug
alert prints the link on the page that asked; with `outbox` it waits on
`/admin/outbox`.

**Admins** — seeded only, never created by signing in.

| Email                        | Name           |
| ---------------------------- | -------------- |
| `jonathan-beebe@outlook.com` | Jonathan Beebe |
| `annaschmunk@pm.me`          | Anna Schmunk   |

**Sellers** — all four verified, each with a shop and part of a 29-listing
catalog across six media (painting, print, ceramic, textile, sculpture,
photography): 24 `for_sale` (one, "Night Freight", carries an admin's
temporary removal and is off the storefront despite its status), 3 `draft`,
2 `sold`.

| Email               | Name        | Shop                       |
| ------------------- | ----------- | -------------------------- |
| `maya@example.com`  | Maya Reyes  | Terra & Glaze Ceramics     |
| `noah@example.com`  | Noah Chen   | North Light Editions       |
| `priya@example.com` | Priya Anand | Priya Anand Textile Studio |
| `leo@example.com`   | Leo Martins | Leo Martins Photography    |

**Customers**

| Email                                 | State                                                                                                      |
| ------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `casey@example.com` (Casey Whitfield) | Verified. 3 favorites, 6 viewed listings, a standing cart with 2 items, and 3 single-item orders in `paid` |
|                                       | (awaiting shipment), `shipped`, and `delivered` states — the delivered one's escrow is released and paid   |
|                                       | out in the weekly payout run.                                                                              |
| `jordan@example.com`                  | Verified, blocked by an admin (`customer_blocks`, reason "Repeated chargebacks reported by two sellers."). |
| _(3 anonymous)_                       | No address given; each has view history on a few listings.                                                 |

## Commands

Every target is a thin `docker compose` wrapper, so either form works.

| Make              | Docker Compose                                                                                        |
| ----------------- | ----------------------------------------------------------------------------------------------------- |
| `make up`         | `docker compose up -d`                                                                                |
| `make down`       | `docker compose down`                                                                                 |
| `make build`      | `docker compose build`                                                                                |
| `make assets`     | `docker compose run --rm app npm run assets`                                                          |
| `make shell`      | `docker compose run --rm app bash`                                                                    |
| `make test`       | `docker compose run --rm app npm run coverage`                                                        |
| `make smoke`      | `docker compose run --rm app node --test app/test/smoke.test.ts`                                      |
| `make coverage`   | `docker compose run --rm app npm run coverage`                                                        |
| `make lint`       | `docker compose run --rm app npm run lint`                                                            |
| `make lint-fix`   | `docker compose run --rm app npm run lint:fix`                                                        |
| `make check`      | `docker compose run --rm app npm run check`                                                           |
| `make sweep`      | `docker compose run --rm app npm run sweep -- $(if $(AS_OF),--as-of=$(AS_OF))`                        |
| `make docs-check` | `./docker/docs-check.sh`                                                                              |
| `make routes`     | `docker compose run --rm app npm run routes`                                                          |
| `make migrate`    | `docker compose run --rm app npm run migrate`                                                         |
| `make fresh`      | `docker compose stop app`, then `npm run fresh`, then `npm run seed`, then `docker compose start app` |
| `make seed`       | `docker compose run --rm app npm run seed`                                                            |
| `make payouts`    | `docker compose run --rm app npm run payouts -- $(if $(AS_OF),--as-of=$(AS_OF))`                      |
| `make outbox`     | `docker compose run --rm app npm run outbox -- $(if $(DIR),--dir=$(DIR))`                             |
| `make logs`       | `docker compose logs -f`                                                                              |
| `make image`      | `docker build --target runtime -t art-store-node .`                                                   |
| `make run-image`  | `docker run --rm -p 4100:4000 art-store-node`                                                         |

The Makefile exports the host `UID` and `GID`, which `docker-compose.yml`
reads to run the container as that user, so files the container writes into
`src/` belong to the host user. A raw `docker compose run` without that
export needs `--user "$(id -u):$(id -g)"`.

`make docs-check` renders every Mermaid block under `docs/` through
`minlag/mermaid-cli` in Docker and fails on any diagram that does not parse.

`make routes` prints every route the app answers and the plugin tree they hang
in, from Fastify's own introspection (`printRoutes` then `printPlugins`) rather
than from a table someone keeps up to date.

## Tests

Tests are sidecars: `foo.ts` gets `foo.test.ts` beside it. `node:test` and
`node:assert/strict` — no test framework is installed. `make test` runs the
coverage-gated suite (see Coverage below). `npm test` on its own runs the
suite without the coverage gate, for a fast local loop. `make lint` runs
`tsc --noEmit` then eslint (`recommendedTypeChecked`, `complexity` max 8,
`max-depth` max 3) — read-only, no gate on the suite itself. `make check`
runs lint, then `make assets`, then the coverage-gated suite, and is the
commit gate `.githooks/pre-commit` and CI both run (see CI below).

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
printing Node's own coverage table, failing under 95% lines / 90% branches,
and writing `coverage/lcov.info` alongside it.

The suite stands at 1,915 tests and 99.43% lines / 95.92% branches / 99.50%
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
installs on Node 24, then runs `npm run check` — the same script
`make check`'s recipe delegates to — and uploads `coverage/lcov.info` as a
build artifact. CI has no compose stack, so it cannot run `make check`
through Docker the way `.githooks/pre-commit` does; running the identical npm
script is how the hook and CI stay unable to disagree. The suite runs once.

## Smoke

```sh
make smoke
```

`app/test/smoke.test.ts` walks the whole product over HTTP, and `make test`
includes it: all three sites serve their own layout off one stylesheet and
`/health` answers its JSON; a seller signs in by magic link and lists a piece; a
fresh anonymous visitor views, favorites, and carts it, checks out as a guest,
verifies the address from the debug alert, is declined once, then pays with
4242; the seller reads the "Item sold" notification and ships; the customer
confirms delivery; an admin runs the weekly payout and the seller's earnings
page shows the net; a customer asks a question and the seller publishes the
answer as an FAQ; an admin removes a listing and it leaves the storefront; an
admin blocks a customer and checkout refuses; an admin messages a seller who
reads it; a sign-in link asked for under outbox delivery is listed on
`/admin/outbox` and drains to an `.eml` file; and one frame comes off a live
`/seller/events` stream over a real socket. Time is frozen so the payout period
reads the same whatever day it runs.

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
still change between Node minors. Backing out is one file: point
`app/db/database.ts` at a compiled driver and Kysely's own `SqliteDialect`
and delete `node-sqlite-dialect.ts`; nothing else imports `node:sqlite`. That
would put a compiler toolchain back in the image, which is what taking it out
bought.

```sh
make migrate    # apply pending migrations
make fresh      # delete the file, rebuild it, seed the admins and demo data, restart the server
make seed       # add the platform admins and demo data; running it twice adds nobody new
```

Every column holding a string union carries a `CHECK` constraint built from the
same `as const` array TypeScript reads, so a status the union does not admit
cannot reach the file (`page_view_counts.site` is the one exception — nothing
but the rollup writes it). Those constraints live in the original `create`
migrations rather than in later ones, so **a database created before they
landed is not upgraded by `make migrate` — run `make fresh`.**

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
`src/public/`. There is no JavaScript bundle. Three of the 71
`app/**/*.ejs` templates carry a `<script>` tag — the three site layouts, each
loading the same `<script defer src="/app.js">` — and no other template has one.
Those 21 dependency-free lines subscribe to `<prefix>/events` and rewrite the
unread-message badge the page already rendered; with JavaScript off the badge is
the number the page was served with and every other flow is unchanged.

## Magic links and the outbox

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

`MAGIC_LINK_DELIVERY` chooses how the link reaches its reader:

| Value    | What happens                                                                                                                            |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| `flash`  | The link comes back in the flash and the debug alert prints it on the page that asked. Development only — a production boot refuses it. |
| `outbox` | The link is queued in `outbox_messages` and nothing is printed. Read it on `/admin/outbox`, or drain it to a file.                      |

## Outbox

Two things need to leave the application: sign-in links and notifications.
Both go through a transactional outbox rather than a mail call at the point
of the business change.

- The row is written **in the same transaction** as the change that caused
  it (`notify` writes the inbox row and the outbox row together;
  `sendMagicLink` writes the link and the outbox row together). A sale that
  rolls back sends nothing.
- Nothing leaves the process inside that transaction. `node:sqlite` is one
  synchronous connection, so an SMTP round trip held inside a transaction would
  block every other request in the process.
- **Draining** is a separate step outside any transaction: it renders each
  pending row as an RFC-5322 message, writes it to
  `<OUTBOX_DIR>/<id>.eml`, and stamps `delivered_at`.

There is no SMTP. A real transport becomes a third `NotificationDelivery` /
`MagicLinkDelivery` implementation and no call site changes.

Drain it three ways, all the same code path (`drainOutbox`,
`app/actions/outbox/drain-outbox.ts`):

```sh
make outbox                      # or: npm run outbox
make outbox DIR=/tmp/mail        # or: npm run outbox -- --dir=/tmp/mail
```

…or the **Drain the outbox** button on `/admin/outbox`.

| Page                                                               | Route                      |
| ------------------------------------------------------------------ | -------------------------- |
| Queued messages, newest first, with a Pending/Sent column          | `GET /admin/outbox`        |
| One message, rendered as it would be sent, with its link clickable | `GET /admin/outbox/:id`    |
| Drain                                                              | `POST /admin/outbox/drain` |

`/admin/outbox/:id` doubles as the mailbox for the demo: with
`MAGIC_LINK_DELIVERY=outbox` the debug alert is off, and the sign-in link is
the clickable link on that page.

Message rendering is a pure core function —
`renderMailMessage` (`app/core/notifications/mail-message.ts`) — taking the
`Message-ID` and the date as inputs, so it is unit tested against literal
strings. Headers are `From`, `To`, `Subject`, `Date`, `Message-ID`,
`MIME-Version`, a `text/plain; charset="utf-8"` content type, and
`Content-Transfer-Encoding: 8bit`; every line ends CRLF, and a CR or LF inside a header value is folded to a space so
nothing can open a header of its own.

## Paying

`decideCard(cardNumber)` (`app/core/payments/fake-card.ts`) is the fake card
processor:

| Number                | Result                         |
| --------------------- | ------------------------------ |
| `4242 4242 4242 4242` | approved                       |
| `4000 0000 0000 0002` | declined — generic decline     |
| `4000 0000 0000 9995` | declined — insufficient funds  |
| anything else         | declined — invalid card number |

Every non-digit is stripped, so spaces and dashes are ignored. Only the last
four digits are ever stored, one `payments` row per attempt. A decline
returns the stock to the listing and leaves a retry form on the order page.

## Admin

Sign in at `/admin/login` as either seeded admin; the debug alert prints the
magic link. The console (everything below `/admin`) sits behind
`requireAdmin`.

| Page         | Route                                                                                         |
| ------------ | --------------------------------------------------------------------------------------------- |
| Dashboard    | `GET /admin`                                                                                  |
| Sellers      | `GET /admin/sellers`, `GET /admin/sellers/:id`                                                |
| Customers    | `GET /admin/customers?standing=` (`all` \| `verified` \| `anonymous` \| `blocked`)            |
| Listings     | `GET /admin/listings?status=&seller=&removed=` (`removed` is `any` \| `removed` \| `visible`) |
| Orders       | `GET /admin/orders?status=&customer=`                                                         |
| Fulfillments | `GET /admin/fulfillments?status=&seller=`                                                     |
| Accounting   | `GET /admin/accounting`                                                                       |
| Payouts      | `GET /admin/payouts?seller=`, `POST /admin/payouts`                                           |
| Ledger       | `GET /admin/ledger?seller=&type=`                                                             |
| Stats        | `GET /admin/stats`                                                                            |
| Outbox       | `GET /admin/outbox`, `GET /admin/outbox/:id`, `POST /admin/outbox/drain`                      |
| Messages     | `GET /admin/messages`                                                                         |

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

| `kind`             | Participants      | Opened from                                                                                    |
| ------------------ | ----------------- | ---------------------------------------------------------------------------------------------- |
| `admin_seller`     | admin ↔ seller    | `/seller/support`, or `POST /admin/sellers/:id/messages`                                       |
| `admin_customer`   | admin ↔ customer  | `/support`, or `POST /admin/customers/:id/messages`                                            |
| `fulfillment`      | seller ↔ customer | `POST /seller/orders/:id/messages`, or `POST /orders/:id/fulfillments/:fulfillmentId/messages` |
| `listing_question` | customer ↔ seller | `POST /art/:slug/questions`                                                                    |

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

A refund can leave a seller's available balance negative. Nothing clamps it:
`isPayable` is false while it stands, so the run writes no payout row for that
seller and the negative nets against their later sales until it clears.

## The stale-order sweep

An order a visitor places and never verifies holds its stock. The sweep hands
it back:

```sh
make sweep                      # cancels orders unverified for STALE_ORDER_HOURS (24)
make sweep AS_OF=2026-08-24     # sweeps as though the run happened then
```

It touches `pending_verification` orders only — an order that reached
`awaiting_payment` has a verified customer behind it — and it is idempotent,
because cancelling moves the order out of the status the query reads.

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
  .dockerignore
  Dockerfile            node:24.19.0-bookworm-slim, no compiler toolchain;
                         dev/build/runtime targets — see Deployment
  docker-compose.yml    one service: app, built from the dev target
  docker/
    entrypoint.sh        dev only: install, migrate, seed, build assets, then the container command
    docs-check.sh        renders every Mermaid block under docs/ through mermaid-cli
  Makefile               host-side wrappers over docker compose, plus image/run-image
  docs/                  architecture.md (the spec) + feature docs + review.md
    README.md, architecture.md, identity.md, orders.md, escrow.md,
    messaging.md, admin.md, data-model.md, ontology.md, review.md
  work/                  tickets and journal
    1-inbox/, 2-doing/, 3-done/, journal.md
  src/                   the Node project
    package.json, package-lock.json, tsconfig.json, eslint.config.js
    app/
      server.ts          entry: loads config, opens the database, listens,
                           drains on SIGINT/SIGTERM
      app.ts              buildApp(deps): composition root
      config.ts           env -> typed config
      logging.ts          pino payload options, request id, CLI logger
      log-story.ts        will/doing/did/refused/failed over any logger
      clock.ts             systemClock and fixedClock
      ids.ts               newId: a prefixed ULID from a clock's instant
      core/                functional core, sidecar tests, no I/O:
                           analytics/, auth/, cart/, customers/, escrow/,
                           health/, ids/, listings/, logging/, messaging/,
                           moderation/, notifications/, orders/, payments/,
                           rate-limit/, reports/, security/, shop/,
                           money.ts, status-label.ts, transition-error.ts
      actions/             verbs over ActionContext, one folder per concept:
                           analytics/, auth/, carts/, customers/, escrow/,
                           favorites/, fulfillments/, listings/, messaging/,
                           moderation/, notifications/, orders/, outbox/,
                           rate-limit/, refunds/,
                           action-context.ts, transaction.ts, action-story.ts
      db/                  database.ts, node-sqlite-dialect.ts, migrator.ts,
                           migrate.ts, schema.ts, commerce-schema.ts, count.ts,
                           timestamp.ts, migrations/,
                           seed.ts + seed-admins/catalog/sellers/customers/
                           order-history/messaging/page-views/demo-data.ts
      delivery/            MagicLinkDelivery + NotificationDelivery ports,
                           delivery-context.ts, the flash and outbox
                           implementations, outbox-message.ts
      http/                zod-type-provider.ts (the validator compiler),
                           request-schema.ts (idParams, slugParams,
                           optionalFilter, submittedForm),
                           request-actions.ts (a request as an ActionContext)
      plugins/             csrf.ts, error-pages.ts, events.ts, flash.ts,
                           health.ts, identity.ts, page-views.ts,
                           rate-limit.ts, request-log.ts, root-plugin.ts,
                           security-headers.ts, site-render.ts,
                           unread-messages.ts
      sites/               shop/, seller/, admin/ — each a plugin with routes/,
                           views/, queries/; auth/ is three flat files
                           (index.ts, sign-in-routes.ts, request-origin.ts)
      views/partials/      csrf-field.ejs, debug-alert.ejs, field-error.ejs,
                           flash.ejs, form-error.ejs, form-field.ejs,
                           head.ejs, unread-badge.ejs
      cli/                 run-payouts.ts, drain-outbox.ts, print-routes.ts,
                           sweep-stale-orders.ts, parse-as-of.ts
      test/                build-test-app.ts, commerce-world.ts, log-lines.ts,
                           smoke.test.ts
      assets/app.css       Tailwind source
    public/                app.css (built, not committed), app.js, uploads/
    storage/               development.sqlite3 and outbox/ (neither committed)
```

## Known gaps

Delivery stops at a file on disk: both ports queue to the outbox and the drain
writes `.eml` files, but there is no SMTP. Seeded listings show procedurally
generated SVG placeholders rather than photographs. Shipment tracking is two
free-text fields (carrier, tracking number) with no carrier integration — the
customer confirms their own delivery. Support threads are all routed to the
first admin by id; there is no assignment model. Messages carry no attachments
and no archive. The storefront's 404 page renders signed-out whoever asks. See
[`docs/review.md`](docs/review.md) for the full numbered list of gaps and
suggested next steps.

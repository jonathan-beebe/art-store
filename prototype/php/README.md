# Art Store prototype (PHP / Laravel)

A two-sided art marketplace prototype, served from three sites: a seller
portal at `/seller`, a customer storefront at `/`, and an admin site at
`/admin` for support and moderation. One Laravel app, one SQLite file, and
every page works with JavaScript off — the one script in the tree is a
progressive enhancement, not a requirement.

Read [`docs/architecture.md`](docs/architecture.md) before changing code — it is
the spec for layers, naming, routes, and testing conventions.
[`docs/review.md`](docs/review.md) maps the brief to what shipped.

## Prerequisites

Docker Desktop. Nothing else: PHP 8.3, Composer, Node 20, and SQLite live in the
`app` container.

## First run

```sh
make up
```

The entrypoint copies `.env.example` to `.env`, installs Composer and npm
dependencies, generates the app key, builds the Tailwind CSS, touches
`src/database/database.sqlite`, runs migrations, and links the public storage
disk before starting the server. The first run takes a few minutes while the
image builds and dependencies download; later runs take seconds.

Then open:

- Storefront — <http://localhost:8000/>
- Seller portal — <http://localhost:8000/seller>
- Admin — <http://localhost:8000/admin> (sign-in admits only a seeded admin address)

An empty database shows an empty storefront. `make fresh` loads the demo data.

`make down` stops the stack. `make logs` follows the server output.

## Commands

Every target is a thin `docker compose` wrapper, so either form works.

| Make | Docker Compose |
| --- | --- |
| `make up` | `docker compose up -d` |
| `make down` | `docker compose down` |
| `make build` | `docker compose build` |
| `make assets` | `docker compose run --rm app npm run build` |
| `make shell` | `docker compose run --rm app bash` |
| `make test` | `docker compose run --rm app composer test` (Pest under pcov, gated at 100% of lines) |
| `make smoke` | `docker compose run --rm app php vendor/bin/pest --testsuite Smoke` |
| `make coverage` | `docker compose run --rm app composer test:coverage` |
| `make lint` | `docker compose run ... app lint` (Pint `--test`), then `... app analyse` (PHPStan) |
| `make lint-fix` | `docker compose run --rm --no-deps --entrypoint composer app lint:fix` |
| `make analyse` | `docker compose run --rm --no-deps --entrypoint composer app analyse` |
| `make check` | `lint`, then `assets`, then `test` — the commit gate |
| `make migrate` | `docker compose run --rm app php artisan migrate` |
| `make fresh` | `docker compose run --rm app php artisan migrate:fresh --seed` |
| `make seed` | `docker compose run --rm app php artisan db:seed` |
| `make routes` | `docker compose run --rm app php artisan route:list` |
| `make payouts` | `docker compose run --rm app php artisan payouts:run $(if $(AS_OF),--as-of=$(AS_OF))` |
| `make sweep` | `docker compose run --rm app php artisan orders:sweep` |
| `make outbox` | prints a note — this prototype has no outbox; notifications are in-app |
| `make logs` | `docker compose logs -f` |
| `make image` | `docker build --target runtime -t art-store-php .` — see Deployment |
| `make run-image` | runs the production image on port 8100 — see Deployment |

`make check` runs `lint` (style, then static analysis), then the asset build,
then the coverage-gated test suite, stopping at the first failure. `lint`,
`lint-fix`, and `analyse` skip the container entrypoint (no web server needed
for a static run).

Run any other tool the same way:

```sh
docker compose exec app php artisan tinker      # against the running server
```

## Tests

```sh
make test                                                    # whole suite
make smoke                                                   # the end-to-end walk alone
make check                                                   # lint + assets + test
docker compose run --rm app composer test -- --filter Money  # one class or method
```

1827 tests (4934 assertions), run by Pest — `it()`/`test()` functions, no
PHPUnit classes outside `tests/*TestCase.php`. Tests are sidecars: `Money.php`
and `MoneyTest.php` sit in the same directory. `phpunit.xml` scans `app/`,
`routes/`, and `database/` for `*Test.php` and lists `tests/Arch.php` by name;
there is no `tests/Feature` or `tests/Unit`. `tests/Pest.php` binds `Tests\CommerceTestCase`,
`Tests\StorefrontTestCase`, and `Tests\TestCase` + `RefreshDatabase` to the
sidecar directories they serve. Tabulated input/output shapes are Pest
datasets, declared inline with `->with([...])` or file-local with `dataset()`
at the top of the sidecar — named datasets in `tests/Pest.php` are out of
reach of sidecars under `app/`, so the suite keeps none there.

`tests/Arch.php` enforces the layer rules from `docs/architecture.md`
(`App\Domain` stays pure, `App\Actions` classes are final and invokable,
controllers skip the `DB` facade, no debug calls, strict types everywhere)
plus Pest's `laravel` and `security` presets. `tests/SidecarsTest.php` asserts
every non-abstract class under `app/` has a sidecar test file, against a
shrink-only list of exceptions that is currently empty.

Static analysis (`make analyse`, and half of `make lint`) runs PHPStan/Larastan
at `level: max` over `app`, `database`, `routes`, and `tests` — the sidecar
tests are analysed with the code they cover, and there are no `excludePaths`
and no `ignoreErrors`. `src/phpstan/*.stub` gives PHPStan the types Pest
carries in traits and in `expect()->extend()`: the test case a Pest closure
runs on, the two custom expectations, and the arch DSL. Formatting (Pint,
checked read-only by the other half of `make lint`, auto-fixed by
`make lint-fix`) enforces `declare(strict_types=1)` on every file.

`src/tests/SmokeTest.php` is the exception to the sidecar rule: one HTTP walk of
the whole product — seller sign-in, listing, sale, guest checkout, magic-link
verification, payment, shipment, delivery, weekly payout — with no production
file of its own to sit beside. It is its own `Smoke` testsuite and runs inside
`make test` as well.

`make coverage` prints a text summary and writes HTML to `src/coverage/` (pcov
is in the image). Current: **100.0% of lines**.

## Database

SQLite at `src/database/database.sqlite`, created on first run. Tests use a
separate in-memory database.

```sh
make migrate    # apply new migrations
make fresh      # drop everything, re-migrate, re-seed the demo data
```

Deleting `src/database/database.sqlite` and running `make up` also rebuilds it
from scratch.

## Seeded accounts

`make fresh` seeds two admins, six sellers, one customer, 37 listings, three
orders, one completed payout, and one conversation of each messaging kind.
Every account signs in through the debug magic link (see below).

| Role | Shop / name | Email |
| --- | --- | --- |
| Admin | Jonathan Beebe | jonathan-beebe@outlook.com |
| Admin | Anna Schmunk | annaschmunk@pm.me |
| Seller | The Burrow Craftworks | molly@example.com |
| Seller | Dean Thomas Studio | dean@example.com |
| Seller | Trelawney's Tower Studio | sybill@example.com |
| Seller | Creevey Camera Works | colin@example.com |
| Seller | Longbottom Botanicals | neville@example.com |
| Seller | Lovegood Curiosities | luna@example.com |
| Customer | Hermione Granger | hermione@example.com |

Hermione has three favorites and order history with two sellers: a paid order
awaiting shipment and a delivered, paid-out order with Molly, and a shipped
order with Dean.

Sybill, Hermione, and the admin each have an unread message waiting: Hermione
asked Sybill about "Divination Tower Vase, Tall" on the storefront, Sybill
answered, and that answer is published as the listing's one FAQ entry;
Hermione and Dean have a thread on Hermione's shipped order; Sybill and the
admin have a support thread, and so do Hermione and the admin.

## Magic links

Passwordless on both sides. Ask for a link at `/seller/login` or `/login` with
any email address — the first link for a seller address creates the account.

There is no mailbox. The link is an `App\Notifications\MagicLinkIssued`
notification delivered on `App\Notifications\Channels\SessionFlashChannel`,
which flashes the URL to the session; both layouts render it in the yellow
**debug alert** at the top of the page, so the link is on screen right after
you submit the form. Links expire after 15 minutes and work once.

`config/magic_links.php` → `delivery` picks the channel. Set
`MAGIC_LINK_DELIVERY=mail` and the same notification goes out as email
instead. Seller and customer notifications have their own switch:
`config/notifications.php` → `channels`, `database` by default, or
`NOTIFICATION_CHANNELS=database,mail` to send both.

## Fake cards

`App\Domain\Payments\FakeCard` decides every charge. Spaces and dashes are
ignored; only the last four digits are stored.

| Number | Result |
| --- | --- |
| `4242 4242 4242 4242` | Approved |
| `4000 0000 0000 0002` | Declined — "Your card was declined." |
| `4000 0000 0000 9995` | Declined — "Your card has insufficient funds." |
| anything else | Declined — "That card number is not valid." |

A declined order keeps a retry form on `/orders/{id}` and puts its stock back on
the storefront until the retry succeeds.

## Money and payouts

Prices are integer cents. The platform takes 10% of the item subtotal when the
order is paid; the seller's net is held in escrow, released when the customer
confirms delivery, and paid out for the last completed Monday–Sunday week:

```sh
docker compose run --rm app php artisan payouts:run                  # as of today
docker compose run --rm app php artisan payouts:run --as-of=2026-07-16
```

`/seller/earnings` also carries a "Run weekly payout now" button. It is a debug
control: it settles every seller, not just the one signed in.

## Styling

Tailwind v4 through Vite. The entrypoint runs `npm run build` on every start, so
`make down && make up` picks up Blade changes. To rebuild without restarting:

```sh
make assets
```

Blade templates reference the build with `@vite(['resources/css/app.css'])`; the
compiled file lands in `src/public/build/`, which is not committed. There is no
JavaScript bundle.

## JavaScript

One file, `src/public/live-badge.js`, ~20 dependency-free lines served
directly rather than through Vite. All three layouts load it with
`<script defer>` and it does one thing: open an `EventSource` against the
site's `/events` route and update the "Messages" nav link's count when a new
message arrives while the page is open. It returns immediately when
`EventSource` is undefined, and every page renders its own correct count from
the server on every load regardless — sign in, browse, message, checkout, and
every other action is a form POST plus a redirect, and all of it works with
JavaScript disabled. See `docs/messaging.md` § "The live badge" for the
stream's shape and cost.

## Layout

```
prototype/php/
  README.md            this file
  Dockerfile           php:8.3-cli + composer + node 20 + gd + pcov + sqlite
  docker-compose.yml   one service: app
  docker/entrypoint.sh first-run setup, then the container command
  Makefile             host-side wrappers over docker compose
  docs/                architecture, diagrams, and the review against the brief
  work/                tickets and journal
  src/                 the Laravel application
    app/Domain/        pure domain core, sidecar tests beside each class
    app/Actions/       one job each, sequencing core + models
    app/Models/        Eloquent: relations, casts, scopes, invariant writes
    app/Http/          controllers, form requests and middleware per site:
                       Shop/, Seller/, Admin/, Auth/
    app/Policies/      ownership and "is this form worth offering"
    app/Events/        past-tense business moments
    app/Listeners/     who hears about an event
    app/Notifications/ what they are told, plus Channels/
    app/View/Composers/ per-site layout data: cart count, notifications, unread messages
    app/Support/       CustomerIdentity, ActorDisplay, UnreadCountStream, PlaceholderImage
    routes/            web.php requires auth.php, shop.php, seller.php, admin.php
    resources/views/   components/layouts/{shop,seller,admin}, components/debug-alert,
                       components/messaging/{inbox,thread,body-form}, components/listing-card,
                       components/form/field, and a page per route under shop/, seller/, admin/
    public/            live-badge.js, served directly rather than through Vite
    phpstan/           stub files that type Pest's traits for the analyser
    tests/             base test cases, Pest bindings, Arch, Sidecars, Smoke
```

## Deployment

`Dockerfile` has four targets. `dev` is today's bind-mount workflow — `make
build` and `make up` build this target, unchanged. `build` installs the
production vendor tree and compiles the Vite bundle at image build time
rather than at container start. `runtime` is the production image: no bind
mount, `APP_ENV=production`, `USER www-data`, a `HEALTHCHECK` against `/up`,
and the SQLite file at `storage/production.sqlite3` so the one declared
volume (`/var/www/src/storage`) holds the database and the uploaded listing
images together.

Build it:

```sh
make image
```

Equivalent to `docker build --target runtime -t art-store-php .` from
`prototype/php`.

The image boots through `composer run deploy`: recreate the storage skeleton
(a freshly mounted volume starts empty), `migrate --force`, `db:seed --force`
(the demo half skips itself once a seller row exists, so the chain re-runs on
every boot), then `artisan serve` on port 8000. On Render, set the Docker
Command to `composer run deploy` — the field does not pass through a shell,
and composer supplies the shell for the chain.

Debug mode for a deploy with no mail service: nothing to set. The image
keeps the default `MAGIC_LINK_DELIVERY=session`, so the sign-in link renders
in the debug banner on the page that asked for it, and the deploy chain
seeds the demo catalog and accounts on first boot. Behind Render's proxy,
set `TRUSTED_PROXIES=*` so generated URLs and cookies follow the forwarded
https scheme.

Run it locally:

```sh
make run-image APP_KEY=<base64 key>
```

Equivalent to
`docker run --rm -p 8100:8000 -e APP_KEY=<key> art-store-php composer run deploy`
(port 8100, so it never collides with `make up`'s 8000). `APP_KEY` is the one
variable with no default; mint one with
`docker run --rm art-store-php php artisan key:generate --show`. Mount the
declared volume to persist state across restarts:

```sh
docker run --rm -p 8100:8000 \
  -v art-store-php-storage:/var/www/src/storage \
  -e APP_KEY=<base64 key> \
  art-store-php composer run deploy
```

## Known gaps

Full list with next steps in [`docs/review.md`](docs/review.md).

- Email delivery is a hook, not an implementation. Every notification has a
  `toMail()` and `MAGIC_LINK_DELIVERY=mail` / `NOTIFICATION_CHANNELS=database,mail`
  switch the channel, but `MAIL_MAILER` points at `log`.
- Shipment tracking is a free-text carrier and number. The customer confirms
  delivery from the order page in place of carrier tracking.
- Seeded listings render a generated placeholder SVG rather than artwork.
- Each open messaging tab holds an SSE worker for as long as it stays open;
  `PHP_CLI_SERVER_WORKERS=16` is what bounds concurrent readers. A closed
  tab's worker comes back within one tick. See `docs/messaging.md`.
- A merged cart keeps a line whose listing carries an active removal, at
  whatever quantity it clamps to; checkout refuses it and names the item
  rather than the merge dropping it.

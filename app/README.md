# Art Store

A two-sided art marketplace, served from three sites: a seller portal at
`/seller`, a customer storefront at `/`, and an admin site at `/admin` for
support and moderation. One Laravel app, three SQLite files. Every page
works with JavaScript off; the seven scripts under `src/public/` are
progressive enhancement.

Read [`docs/architecture.md`](docs/architecture.md) before changing code — it is
the spec for layers, naming, routes, and testing conventions.

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
disk before starting the server. Local `make up` migrates only; the
production deploy chain (see Deployment) migrates and seeds. The first run
takes a few minutes while the image builds and dependencies download; later
runs take seconds.

Then open:

- Storefront — <http://localhost:8000/>
- Seller portal — <http://localhost:8000/seller>
- Admin — <http://localhost:8000/admin> (sign-in admits only a seeded admin address)

An empty database shows an empty storefront. `make fresh` loads the demo data.

`make down` stops the stack. `make logs` follows the server output.

## Commands

Most targets wrap `docker compose`, so either column below works. `image`
and `run-image` wrap `docker build` and `docker run`; `outbox` prints a
notice. Bare `make` (or `make help`) prints this list with a one-line
description per target.

| Make             | Docker Compose                                                                                 |
| ---------------- | ---------------------------------------------------------------------------------------------- |
| `make up`        | `docker compose up -d`                                                                         |
| `make down`      | `docker compose down`                                                                          |
| `make build`     | `docker compose build`                                                                         |
| `make assets`    | `docker compose run --rm app npm run build`                                                    |
| `make shell`     | `docker compose run --rm app bash`                                                             |
| `make test`      | `docker compose run --rm app composer test` (the full Pest suite, ungated)                     |
| `make smoke`     | `docker compose run --rm app php vendor/bin/pest --testsuite Smoke`                            |
| `make coverage`  | `docker compose run --rm app composer test:coverage` (Pest under pcov, gated at 95% of lines)  |
| `make lint`      | `docker compose run --rm --no-deps --entrypoint composer app lint:all` (Pint `--test`, then PHPStan) |
| `make lint-fix`  | `docker compose run --rm --no-deps --entrypoint composer app lint:fix`                         |
| `make analyse`   | `docker compose run --rm --no-deps --entrypoint composer app analyse`                          |
| `make precommit` | `docker compose run --rm app sh -c "composer lint:all && composer test"` — the per-commit gate  |
| `make ci`        | `lint`, then `test` — what CI runs on every push and PR                                        |
| `make check`     | `lint`, then `assets`, then `coverage` — the pre-PR gate, run locally                          |
| `make migrate`   | `docker compose run --rm app php artisan migrate`                                              |
| `make fresh`     | clears every database and re-seeds the demo data                                               |
| `make seed`      | `docker compose run --rm app php artisan db:seed`                                              |
| `make seed-activity` | `docker compose run --rm app php artisan seed:activity` — fills the store with a ninety-day activity ramp (local dev only) |
| `make routes`    | `docker compose run --rm app php artisan route:list`                                           |
| `make payouts`   | `docker compose run --rm app php artisan payouts:run $(if $(AS_OF),--as-of=$(AS_OF))`          |
| `make sweep`     | `docker compose run --rm app php artisan orders:sweep`                                         |
| `make outbox`    | prints that the app has no outbox; notifications are in-app                                    |
| `make logs`      | `docker compose logs -f`                                                                       |
| `make image`     | `docker build --target runtime -t art-store-php .` — see Deployment                            |
| `make run-image` | runs the production image on port 8100 — see Deployment                                        |

`make check` runs `lint` (style, then static analysis), then the asset build,
then the coverage-gated test suite, stopping at the first failure. `lint`,
`lint-fix`, and `analyse` skip the container entrypoint (no web server needed
for a static run).

## Commit gate

`make hooks` (run once, at the repository root) installs
`.githooks/pre-commit`, which runs `make precommit` for a commit touching
`app/` outside `app/docs/` and `app/README.md`: lint, then the ungated test
suite, in one container spawn. `make check` — lint, the asset build, then the
coverage-gated suite — runs once per branch before a PR opens rather than on
every commit, by hand. CI (`.github/workflows/check.yml`) runs `make ci`,
lint and the ungated suite, on push/PR; coverage stays out of CI because
instrumenting the suite is the slowest step. A red test suite blocks a
commit either way.

Run any other tool the same way:

```sh
docker compose exec app php artisan tinker      # against the running server
```

## Tests

```sh
make test                                                    # whole suite
make smoke                                                   # the end-to-end walk alone
make check                                                   # lint + assets + coverage, before a PR
make ci                                                      # lint + tests, what CI runs
docker compose run --rm app composer test -- --filter Money  # one class or method
```

Pest runs the suite; the last line of `make test` output carries the test
and assertion counts. Tests are `it()`/`test()` functions, and the only
PHPUnit classes are the base cases in `tests/*TestCase.php`. Tests are
sidecars: `Money.php`
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
shrink-only list of exceptions (classes covered by another file's tests, or
with no behavior of their own).

Static analysis (`make analyse`, and half of `make lint`) runs PHPStan/Larastan
at `level: max` over `app`, `database`, `routes`, and `tests` — the sidecar
tests are analysed with the code they cover, and there are no `excludePaths`
and no `ignoreErrors`. `src/phpstan/*.stub` gives PHPStan the types Pest
carries in traits and in `expect()->extend()`: the test case a Pest closure
runs on, the two custom expectations, and the arch DSL. Formatting (Pint,
checked read-only by the other half of `make lint`, auto-fixed by
`make lint-fix`) enforces `declare(strict_types=1)` on every file.

`src/tests/SmokeTest.php` and `src/tests/ConfiguratorSmokeTest.php` are the
exceptions to the sidecar rule: one HTTP walk of the whole product — seller
sign-in, listing, sale, guest checkout, magic-link verification, payment,
shipment, delivery, weekly payout — and two walks of the item configurator,
with no production file of their own to sit beside. The `Smoke` testsuite is
every `*Test.php` under `tests/`; `make smoke` runs it alone and `make test`
includes it.

`make coverage` runs the suite under pcov, fails under 95% of lines
(`--min=95`), prints a text summary, and writes HTML to `src/coverage/`
(pcov is in the image). The summary's last line carries the current figure.

## Database

SQLite at `src/database/database.sqlite`, created on first run. Two sibling
SQLite files hold data the commerce database never sees:
`src/storage/logs.sqlite3` (the mirrored log store, `LOG_DATABASE_FILE`) and
`src/storage/analytics.sqlite3` (page views and analytics events,
`ANALYTICS_DATABASE_FILE`). Tests run the commerce and analytics connections
in memory and leave the log store off.

```sh
make migrate    # apply new migrations
make fresh      # clear every database, re-seed the demo data
```

`make fresh` clears all three and re-seeds the demo data.

## Seeded accounts

`make fresh` seeds two admins, seven sellers, two customers, 46 listings, three
orders, one completed payout, and five conversations — one of each messaging
kind, plus a second listing question. Every account signs in through the
debug magic link (see below).

| Role     | Shop / name              | Email                      |
| -------- | ------------------------ | -------------------------- |
| Admin    | Jonathan Beebe           | jonathan-beebe@outlook.com |
| Admin    | Anna Schmunk             | annaschmunk@pm.me          |
| Seller   | The Burrow Craftworks    | molly@example.com          |
| Seller   | Dean Thomas Studio       | dean@example.com           |
| Seller   | Trelawney's Tower Studio | sybill@example.com         |
| Seller   | Creevey Camera Works     | colin@example.com          |
| Seller   | Longbottom Botanicals    | neville@example.com        |
| Seller   | Lovegood Curiosities     | luna@example.com           |
| Seller   | Weasleys' Wizard Wheezes | george@example.com         |
| Customer | Hermione Granger         | hermione@example.com       |
| Customer | Luna Lovegood            | luna@example.com           |

Hermione has three favorites and order history with two sellers: a paid order
awaiting shipment and a delivered, paid-out order with Molly, and a shipped
order with Dean.

Five threads seed the messaging centre: Hermione asked Sybill about
"Divination Tower Vase, Tall" on the storefront, Sybill answered, Hermione's
thank-you reply sits unread for Sybill, and the answer is published as the
listing's one FAQ entry; Luna (the customer — she shares an email with the
Lovegood Curiosities seller row) asked Molly about "Burrow Kitchen Tea Bowl"
and it sits unread and unanswered; Hermione and Dean have an unread exchange
on Hermione's shipped order; Sybill's resolved "Payout timing" thread with
Art Store Support carries one admin reply she has not read; and Hermione's
open "Missing confirmation email" thread, tied to her awaiting-shipment
order, is waiting on the desk.

`make seed-activity`, run once after `make fresh`, adds ninety-plus days of
store activity ending now: verified signups ramping from a handful to a
surge across the three months (near 8 / 30 / 80 on a real 92-day run),
anonymous visitors browsing, favoriting, and abandoning or completing
carts, some of them verifying partway through and folding their history
into a signed-up account, sellers creating and publishing new listings
(the catalog grows from 46 toward 150+), orders placed, paid, shipped,
delivered, or cancelled, listing questions and support conversations, and
weekly payouts — everything `/admin/analytics` and the seller and admin
portals need to show a store that has been open for a season. Two
anonymous visitors behave badly: a scraper hammering listing pages fast
enough to trip the fraud lens's velocity flag, and a prober scanning for
`.env`, `wp-login.php`, `/admin`, and similar paths, findable from the
analytics actor search (by ip) and `/admin/logs` (see `docs/analytics.md`
§ "Seeded activity"). It refuses in production and refuses a second run
against a database that already carries its marker row (`seed_runs`);
every person it names is drawn from `App\Domain\Seeding\HogwartsRoster`,
the same Harry Potter universe rule as the rest of the demo data.

## Magic links

Passwordless on both sides. Ask for a link at `/seller/login` or `/login` with
any email address — the first link for a seller address creates the account.

There is no mailbox. The link is an `App\Notifications\MagicLinkIssued`
notification delivered on `App\Notifications\Channels\SessionFlashChannel`,
which flashes the URL to the session; the `shop`, `seller`,
`seller-focused`, `admin`, and `auth` layouts render it in the yellow
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

| Number                | Result                                         |
| --------------------- | ---------------------------------------------- |
| `4242 4242 4242 4242` | Approved                                       |
| `4000 0000 0000 0002` | Declined — "Your card was declined."           |
| `4000 0000 0000 9995` | Declined — "Your card has insufficient funds." |
| anything else         | Declined — "That card number is not valid."    |

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

`POST /admin/payouts` (the admin payouts page) runs the same action for
every seller.

## Styling

Tailwind v4 through Vite. The entrypoint rebuilds the CSS when any Tailwind
input has changed since the last build, so `make down && make up` picks up
Blade changes. To rebuild without restarting:

```sh
make assets
```

Blade templates reference the build with `@vite(['resources/css/app.css'])`; the
compiled file lands in `src/public/build/`, which is not committed. There is no
JavaScript bundle.

## JavaScript

Seven dependency-free scripts under `src/public/`, served directly and
outside Vite. Each layout or view that needs one loads it with
`<script defer>`: `composer.js`, `configurator-autosubmit.js`,
`sort-autosubmit.js`, `nav-drawer.js`, `new-listing-modal.js`,
`listing-detail-dialog.js`, and `print-button.js`.

`composer.js` (38 lines) does three things for every message composer
(seller, shop, admin): swaps the keyboard hint to ⌘ on a Mac, updates the
live character counter as you type, and lets Cmd/Ctrl+Enter submit the
form. The textarea grows through CSS (`field-sizing: content`). A message
composer's counter is server-rendered first and `Enter` alone stays a
newline, so posting a message works the same with JavaScript disabled. See
`docs/messaging.md` § "The composer" for the shared contract.

## Layout

```
app/
  README.md            this file
  Dockerfile           php:8.3-cli dev/build targets + FrankenPHP runtime
  docker-compose.yml   one service: app
  docker/              Caddyfile, entrypoint.sh (first-run setup, then the container command), pcov.ini
  Makefile             host-side wrappers over docker compose
  docs/                architecture, diagrams, and reference docs
  src/                 the Laravel application
    app/Domain/        pure domain core, sidecar tests beside each class
    app/Actions/       one job each, sequencing core + models
    app/Models/        Eloquent: relations, casts, scopes, invariant writes
    app/Http/          controllers, form requests and middleware per site:
                       Shop/, Seller/, Admin/, Auth/
    app/Policies/      ownership and "is this form worth offering"
    app/Events/        past-tense business moments
    app/Listeners/     who hears about an event
    app/Observers/     LedgerEntryObserver: the ledger.write log line
    app/Notifications/ what they are told, plus Channels/
    app/Console/       artisan commands: payouts:run, orders:sweep, seed:activity
    app/Seller/        page-shaped readers for the seller portal
    app/Admin/         page-shaped readers for the admin site
    app/Analytics/     the Analytics entry point, its rows, and the admin readers
    app/Logging/       StoryFormatter, StoryEvent, the log store and its tap
    app/View/Composers/ per-site layout data: cart count, notifications, unread messages
    app/View/Components/ class-backed Blade components: stat tile, list pane row, bar strip
    app/Support/       CustomerIdentity, ActorDisplay, PlaceholderImage
    routes/            web.php requires auth.php, shop.php, seller.php, admin.php
    resources/views/   components/layouts/{shop,seller,seller-focused,admin,auth,error}, components/debug-alert,
                       components/messaging/* (shared by admin and seller), components/seller/messaging/*,
                       components/shop/messaging/*, components/listing-card,
                       components/form/field, and a page per route under shop/, seller/, admin/
    public/            seven vanilla scripts, served directly and outside Vite
    phpstan/           stub files that type Pest's traits for the analyser
    tests/             base test cases, Pest bindings, Arch, Sidecars, Smoke
```

## Deployment

`Dockerfile` has four targets. `dev` is today's bind-mount workflow — `make
build` and `make up` build this target, unchanged. `build` installs the
production vendor tree and compiles the Vite bundle at image build time
rather than at container start. `runtime` is the production image, rebased
on the official FrankenPHP PHP 8.3 image rather than `dev`'s `php:8.3-cli`:
no bind mount, `APP_ENV=production`, `USER www-data`, a `HEALTHCHECK`
against `/up`, and the SQLite file at `storage/production.sqlite3` so the
one declared volume (`/var/www/src/storage`) holds the database and the
uploaded listing images together. FrankenPHP serves `public/build/*` and
`public/storage/*` itself, from `docker/Caddyfile` — those requests never
occupy a PHP process — and hands every other request to PHP in classic
per-request mode, the same as `artisan serve`; Octane-style worker mode is
not in use, since `App\Logging\LogStore` assumes one request per process.
Capacity is governed by FrankenPHP's thread pool. `docker/Caddyfile` sets
the floor at 4 threads (`FRANKENPHP_NUM_THREADS` overrides) with on-demand
growth to 8 (`FRANKENPHP_MAX_THREADS`), because FrankenPHP's own default
(2×CPU) is two threads on a one-CPU instance. These two variables are the
knob.

Build it:

```sh
make image
```

Equivalent to `docker build --target runtime -t art-store-php .` from `app`.

The image boots through `composer run deploy`: recreate the storage skeleton
(a freshly mounted volume starts empty), `migrate --force`, `db:seed --force`
(the demo half skips itself once a seller row exists, so the chain re-runs on
every boot), then `frankenphp run` on port 8000. On Render, set the Docker
Command to `composer run deploy` — the field does not pass through a shell,
and composer supplies the shell for the chain.

Debug mode for a deploy with no mail service: nothing to set. The image
keeps the default `MAGIC_LINK_DELIVERY=session`, so the sign-in link renders
in the debug banner on the page that asked for it, and the deploy chain
seeds the demo catalog and accounts on first boot. Behind Render's proxy,
set `TRUSTED_PROXIES=*` so generated URLs and cookies follow the forwarded
https scheme.

That variable is Laravel's own trust and unrelated to Caddy's: Caddy only
honors forwarded headers from a remote it trusts, and its `static` IP-range
list has no `*` wildcard. `docker/Caddyfile`'s `trusted_proxies` defaults to
the private-address ranges a same-host or private-network proxy connects
from; set `CADDY_TRUSTED_PROXIES` (space-separated CIDRs or addresses) to
add a fronting platform's own address when it connects from outside those
ranges.

Run it locally:

```sh
make run-image APP_KEY=<base64 key>
```

Equivalent to
`docker run --rm --cap-drop=ALL --security-opt no-new-privileges -p 8100:8000 -e APP_KEY=<key> art-store-php composer run deploy`
(port 8100, so it never collides with `make up`'s 8000). The `--cap-drop` and
`--security-opt` flags emulate the restrictions of Render's sandboxed
production runtime — no capabilities, no privilege gains — so a boot that
passes here predicts a boot that passes there. `APP_KEY` is the one
variable with no default; mint one with
`docker run --rm art-store-php php artisan key:generate --show`. Mount the
declared volume to persist state across restarts:

```sh
docker run --rm --cap-drop=ALL --security-opt no-new-privileges \
  -p 8100:8000 \
  -v art-store-php-storage:/var/www/src/storage \
  -e APP_KEY=<base64 key> \
  art-store-php composer run deploy
```

## Known gaps

- Email delivery is a hook, not an implementation. Every notification has a
  `toMail()` and `MAGIC_LINK_DELIVERY=mail` / `NOTIFICATION_CHANNELS=database,mail`
  switch the channel, but `MAIL_MAILER` points at `log`.
- Shipment tracking is a free-text carrier and number. The customer confirms
  delivery from the order page in place of carrier tracking.
- A listing with no photograph renders a generated placeholder SVG.
- The unread badge is a server-rendered count; a new message shows on the
  next page load. See `docs/messaging.md` § "Unread counts".
- A merged cart keeps a line whose listing carries an active removal, at
  whatever quantity it clamps to; checkout refuses it and names the item.

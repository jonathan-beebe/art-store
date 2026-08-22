# Art Store prototype (PHP / Laravel)

A two-sided art marketplace prototype: a seller portal at `/seller` and a
customer storefront at `/`. One Laravel app, one SQLite file, no JavaScript
required.

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
| `make test` | `docker compose run --rm app composer test` |
| `make smoke` | `docker compose run --rm app composer test -- --testsuite Smoke` |
| `make coverage` | `docker compose run --rm app composer test:coverage` |
| `make migrate` | `docker compose run --rm app php artisan migrate` |
| `make fresh` | `docker compose run --rm app php artisan migrate:fresh --seed` |
| `make logs` | `docker compose logs -f` |

Run any other tool the same way:

```sh
docker compose run --rm app php artisan route:list
docker compose run --rm app php artisan payouts:run --as-of=2026-07-16
docker compose exec app php artisan tinker      # against the running server
```

## Tests

```sh
make test                                                    # whole suite
make smoke                                                   # the end-to-end walk alone
docker compose run --rm app composer test -- --filter Money  # one class or method
```

471 tests. Tests are sidecars: `Money.php` and `MoneyTest.php` sit in the same
directory. `phpunit.xml` scans `app/`, `routes/`, and `database/` for
`*Test.php`; there is no `tests/Feature` or `tests/Unit`. Domain tests extend
`PHPUnit\Framework\TestCase`; HTTP tests extend `Tests\TestCase`.

`src/tests/SmokeTest.php` is the exception to the sidecar rule: one HTTP walk of
the whole product — seller sign-in, listing, sale, guest checkout, magic-link
verification, payment, shipment, delivery, weekly payout — with no production
file of its own to sit beside. It is its own `Smoke` testsuite and runs inside
`make test` as well.

`make coverage` prints a text summary and writes HTML to `src/coverage/` (pcov
is in the image). Current: **98.20% lines overall, 100% on `app/Domain`**.

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

`make fresh` seeds four sellers, one customer, 29 listings, three orders, and
one completed payout. Every account signs in through the debug magic link (see
below).

| Role | Shop / name | Email |
| --- | --- | --- |
| Seller | Terra & Glaze Ceramics | maya@example.com |
| Seller | North Light Editions | noah@example.com |
| Seller | Priya Anand Textile Studio | priya@example.com |
| Seller | Leo Martins Photography | leo@example.com |
| Customer | Casey Whitfield | casey@example.com |

Casey has three favorites and order history with two sellers: a paid order
awaiting shipment and a delivered, paid-out order with Maya, and a shipped
order with Noah.

## Magic links

Passwordless on both sides. Ask for a link at `/seller/login` or `/login` with
any email address — the first link for a seller address creates the account.

There is no mailbox. `SessionFlashMagicLinkDelivery` flashes the URL to the
session and both layouts render it in the yellow **debug alert** at the top of
the page, so the link is on screen right after you submit the form. Links
expire after 15 minutes and work once.

The email hook is `config/magic_links.php` → `delivery`. Set
`MAGIC_LINK_DELIVERY=mail` and it binds
`App\Support\MagicLinkDelivery\MailMagicLinkDelivery`, which currently throws —
implement `deliver()` there. In-app notifications carry the same hook:
`App\Actions\Notifications\Notify::deliverByEmail()`.

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
JavaScript bundle and no `<script>` tag in any view.

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
    app/Http/          controllers per site: Shop/, Seller/, Auth/
    routes/            web.php requires auth.php, shop.php, seller.php
    resources/views/   layouts/shop, layouts/seller, partials/debug-alert
    tests/             base test cases and SmokeTest
```

## Known gaps

Full list with next steps in [`docs/review.md`](docs/review.md).

- Email delivery is a hook, not an implementation. `MAGIC_LINK_DELIVERY=mail`
  throws.
- Four action classes under `app/Actions/Auth` and `app/Actions/Customers` have
  no sidecar test. All four are at 100% line coverage through the controller
  tests that drive them.
- Eloquent models sit at 86.07% line coverage. The uncovered lines are inverse
  `belongsTo` relations no caller reads yet.
- No order cancellation route; `OrderStatus::Cancelled` exists in the domain
  with no way to reach it over HTTP.
- Shipment tracking is a free-text carrier and number. The customer confirms
  delivery from the order page in place of carrier tracking.
- Seeded listings render a generated placeholder SVG rather than artwork.

# Art Store prototype (Rails)

A two-sided art marketplace prototype: a seller portal at `/seller` and a
customer storefront at `/`. One Rails app, one SQLite file, no JavaScript.

Read [`docs/architecture.md`](docs/architecture.md) before changing code — it is
the spec for layers, naming, routes, and testing conventions.

## Prerequisites

Docker Desktop. Nothing else: Ruby 3.3, Rails 8.1, SQLite, and the Tailwind
standalone binary live in the `app` container.

## First run

```sh
make up
```

The entrypoint installs the bundle into `src/vendor/bundle`, prepares
`src/storage/development.sqlite3`, builds the Tailwind stylesheet, and clears a
stale server pid before starting Puma. The first run takes a few minutes while
the image builds and gems compile; later runs take seconds.

Then open:

- Storefront — <http://localhost:3000/>
- Seller portal — <http://localhost:3000/seller>

`make down` stops the stack. `make logs` follows the server output.

## Seeded accounts

`bin/rails db:seed` (part of `make fresh`, and run once automatically by the
entrypoint against a fresh database) creates four verified sellers, 29
listings, one verified customer, and order history built through the FEAT-003
actions: a paid order awaiting shipment, a shipped order, and a delivered
order whose escrow is released and paid out.

| Email | Role | Shop |
| --- | --- | --- |
| `maya@example.com` | Seller | Terra & Glaze Ceramics |
| `noah@example.com` | Seller | North Light Editions |
| `priya@example.com` | Seller | Priya Anand Textile Studio |
| `leo@example.com` | Seller | Leo Martins Photography |
| `casey@example.com` | Customer | — (3 favorites, view history, 3 orders) |

Every account is passwordless. Sign in at `/seller/login` or `/login` with one
of the emails above; the layout's debug alert prints the magic link in place
of sending an email, and clicking it signs you in.

## Commands

Every target is a thin `docker compose` wrapper, so either form works.

| Make | Docker Compose |
| --- | --- |
| `make up` | `docker compose up -d` |
| `make down` | `docker compose down` |
| `make build` | `docker compose build` |
| `make assets` | `docker compose run --rm app bin/rails tailwindcss:build` |
| `make shell` | `docker compose run --rm app bash` |
| `make test` | `docker compose run --rm app bin/rails test` |
| `make smoke` | `docker compose run --rm app bin/rails test test/smoke_test.rb` |
| `make coverage` | `docker compose run --rm -e COVERAGE_MIN=80 app bin/rails test` |
| `make migrate` | `docker compose run --rm app bin/rails db:migrate` |
| `make fresh` | `docker compose run --rm app bin/rails db:drop db:create db:migrate db:seed` |
| `make console` | `docker compose run --rm app bin/rails console` |
| `make logs` | `docker compose logs -f` |

Run any other command the same way:

```sh
docker compose run --rm app bin/rails routes
docker compose exec app bin/rails console      # against the running server
```

## Tests

Minitest, and every test lives under `test/`, mirroring `app/`:
`app/domain/money.rb` is covered by `test/domain/money_test.rb`. `bin/rails
test` with no arguments runs the whole suite. Alongside the mirrored trees,
`test/` holds `test_helper.rb` (the Rails base and the coverage setup),
`support/` for the shared helpers, `smoke_test.rb`, `seeds_test.rb`, and
`tasks/` for the rake tasks. `test/seeds_test.rb` calls
`Rails.application.load_seed` and asserts the seeded counts.

```sh
make test                                                                    # whole suite
docker compose run --rm app bin/rails test test/domain/money_test.rb         # one file
docker compose run --rm app bin/rails test test/domain/money_test.rb -n /percent/   # one test
```

Every test requires `test_helper` and subclasses `ActiveSupport::TestCase`,
`ActionDispatch::IntegrationTest` or `ActionView::TestCase`. Core tests under
`test/domain` touch no database. Controller tests drive HTTP and assert on
rendered HTML. `test/support/test_records.rb` holds the record builders every
test can reach (`create_seller`, `create_listing`, `paid_order_for`, the card
numbers); `test/support/integration_helpers.rb` holds what only the tests
driving HTTP need (`sign_in_as_customer`, `signed_cookie`, `create_fulfillment`).
`test_helper.rb` requires both and mixes them in.

## Smoke

```sh
make smoke
```

`src/test/smoke_test.rb` is one integration test that walks the whole product
over HTTP in two browsers: the seller signs in by magic link, creates a listing
with an image, marks it for sale; a fresh anonymous visitor views, favorites,
and carts it, checks out as a guest, verifies the address from the debug alert,
pays with 4242; the seller reads the "Item sold" notification and ships; the
customer confirms delivery; the weekly payout runs and the earnings page shows
the net. Time is frozen so the payout period is the same whatever day it runs.
It is part of `make test`.

## Coverage

```sh
make coverage
```

SimpleCov writes `src/coverage/` and prints the overall line coverage plus a
line per group (Domain, Actions, Controllers, Models). `COVERAGE_MIN` sets the
overall line minimum and fails the run below it; `make coverage` passes 80. The
suite stands at 645 runs and 100% line coverage.

## Database

SQLite at `src/storage/development.sqlite3`, created on first run. Tests use
`src/storage/test.sqlite3`.

```sh
make migrate    # apply new migrations
make fresh      # drop, create, migrate, re-seed
```

Deleting `src/storage/development.sqlite3` and running `make up` rebuilds it.

## Styling

Tailwind v4 through `tailwindcss-rails`, which ships the standalone binary — no
Node in the image. The entrypoint runs `bin/rails tailwindcss:build` on every
start, so `make down && make up` picks up template changes. To rebuild without
restarting:

```sh
make assets
```

The source is `src/app/assets/tailwind/application.css`; the build lands in
`src/app/assets/builds/tailwind.css`, which is not committed. Layouts reference
it with `stylesheet_link_tag :app`. There is no JavaScript bundle and no
`<script>` tag in any view.

## Layout

```
prototype/rails/
  README.md            this file
  Dockerfile           ruby:3.3-slim + build tools + sqlite + the rails generator
  docker-compose.yml   one service: app
  docker/entrypoint.sh bundle, database, Tailwind, then the container command
  Makefile             host-side wrappers over docker compose
  docs/                architecture, feature docs, and review.md
  work/                tickets and journal
  src/                 the Rails application
    app/domain/        pure domain core
    app/actions/       one class per verb, sequencing core and adapters
    app/controllers/   one namespace per site: shop/, seller/, auth/
    app/delivery/      the magic-link delivery port and its two implementations
    app/views/layouts/ shop, seller, and the _debug_alert partial both render
    config/routes.rb   / and /seller
    test/              mirrors app/: domain/, actions/, controllers/, models/
    test/test_helper.rb SimpleCov and the Rails test base
    test/support/      the record builders and the HTTP sign-in helpers
    test/smoke_test.rb  the whole product in one walk
```

[`docs/review.md`](docs/review.md) maps every requirement in the brief to the
route and the test that prove it, and lists what is missing.

## Magic links

Passwordless on both sides, with no mailbox: the delivery port flashes the URL
and `layouts/_debug_alert` prints it at the top of both layouts whenever
`flash[:debug_magic_link]` is set.

Sellers sign in at `/seller/login`, customers at `/login`; both submit an email
address, then click the link in the debug alert. A link lasts 15 minutes and
works once. The first link for an address creates the account.

Every storefront visitor gets a `customers` row before they give an address,
carried in the signed `customer_id` cookie. Verifying an address either claims
that row or merges it into the account already holding the address.

```sh
MAGIC_LINK_DELIVERY=mail        # flash (default) | mail, which raises until email exists
MAGIC_LINK_EXPIRY_MINUTES=15
```

Two hooks are where email goes when it exists:
`src/app/delivery/mail_magic_link_delivery.rb` (`MailMagicLinkDelivery#deliver`,
selected by `MAGIC_LINK_DELIVERY=mail`) for sign-in links, and
`src/app/actions/notifications/notify.rb` (`Notify#deliver_by_email`) for the
"Item sold" and "Order shipped" notifications, which reach the in-app inbox
today.

## Paying

The card is fake and nothing is stored but the last four digits.

| Number | Result |
| --- | --- |
| `4242 4242 4242 4242` | approved |
| `4000 0000 0000 0002` | declined — generic decline |
| `4000 0000 0000 9995` | declined — insufficient funds |
| anything else | declined — invalid card number |

Spaces and dashes are ignored. A decline leaves the order on a retry form with
the stock returned to the listing.

## Known gaps

The full list, with the next steps for each, is in
[`docs/review.md`](docs/review.md). The ones to know before a demo:

- Email is not implemented. Both hooks above are empty, and
  `MAGIC_LINK_DELIVERY=mail` raises rather than sending.
- "Run weekly payout now" on the earnings page pays every seller, not the
  signed-in one. It is a debug control; `payouts:run` is the real entry point.
- A merge can leave a customer holding two carts, and the storefront shops with
  whichever holds more items.
- There is no order cancellation route.
- There is no libvips in the image, so Active Storage serves original blobs and
  a variant would raise. Seeded listings carry a generated SVG rather than a
  photograph.

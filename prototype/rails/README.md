# Art Store prototype (Rails)

A two-sided art marketplace prototype with a desk behind it: a seller portal at
`/seller`, a customer storefront at `/`, and an admin site at `/admin`. One
Rails app, one SQLite file. Turbo is the only JavaScript, and every page works
without it.

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
- Admin site — <http://localhost:3000/admin>

`make down` stops the stack. `make logs` follows the server output.

## Seeded accounts

`bin/rails db:seed` (part of `make fresh`, and run once automatically by the
entrypoint against a fresh database) creates two admins, four verified sellers,
29 listings, one verified customer, order history built through the FEAT-003
actions (a paid order awaiting shipment, a shipped order, and a delivered
order whose escrow is released and paid out), and messaging: one thread of
each of the four kinds — the desk with a seller, the desk with the customer,
the customer and a seller about the shipped order, and a question on a
listing — nine messages between them, and the one answer published to the
listing as an FAQ. Every thread ends on a message its other side has not
opened, so four badges are waiting: one each for maya and noah in the portal,
two for casey on the storefront.

| Email | Role | Shop |
| --- | --- | --- |
| `jonathan-beebe@outlook.com` | Admin | — (on duty: 2 support threads) |
| `annaschmunk@pm.me` | Admin | — |
| `maya@example.com` | Seller | Terra & Glaze Ceramics |
| `noah@example.com` | Seller | North Light Editions |
| `priya@example.com` | Seller | Priya Anand Textile Studio |
| `leo@example.com` | Seller | Leo Martins Photography |
| `casey@example.com` | Customer | — (3 favorites, view history, 3 orders, 3 threads) |

Every account is passwordless. Sign in at `/seller/login`, `/login`, or
`/admin/login` with one of the emails above; the layout's debug alert prints
the magic link in place of sending an email, and clicking it signs you in.
Sellers and customers sign up by following their first link; admins are seeded,
so an address with no `admins` row is refused at `/admin/login`.

## Commands

Every target runs through `docker compose`; nothing here touches the host.
`sweep` and `outbox` print a line and exit — see below.

| Make | Runs |
| --- | --- |
| `make up` | `docker compose up -d` |
| `make down` | `docker compose down` |
| `make build` | `docker compose build` |
| `make logs` | `docker compose logs -f` |
| `make shell` | `docker compose run --rm app bash` |
| `make test` | `bin/rails db:test:prepare`, then `bin/rails test` with the coverage gate (`COVERAGE_MIN=100`) |
| `make smoke` | `bin/rails db:test:prepare`, then `bin/rails test test/smoke_test.rb` |
| `make coverage` | `bin/rails db:test:prepare`, then `bin/rails test`, writing the HTML report with no gate |
| `make lint` | `bin/rubocop`, read-only |
| `make lint-fix` | `bin/rubocop -a`, the auto-fixable subset |
| `make assets` | `docker compose run --rm app bin/rails tailwindcss:build` |
| `make check` | `lint` → `assets` → `test`; the commit gate and the CI job |
| `make migrate` | `docker compose run --rm app bin/rails db:migrate` |
| `make fresh` | `docker compose run --rm app bin/rails db:drop db:create db:migrate db:seed` |
| `make seed` | `docker compose run --rm app bin/rails db:seed` |
| `make routes` | `docker compose run --rm app bin/rails routes` |
| `make payouts` | the weekly payout rake task; `make payouts AS_OF=2026-08-24` settles the week before that date |
| `make sweep` | prints that the stale-order sweep lands with FEAT-017 — nothing to run yet |
| `make outbox` | prints that Rails has no outbox — notifications and mail are written and delivered in the same request or job |
| `make console` | `docker compose run --rm app bin/rails console` |

Run any other command the same way:

```sh
docker compose run --rm app bin/rails routes
docker compose exec app bin/rails console      # against the running server
```

## Tests

Minitest, and every test lives under `test/`, mirroring `app/`:
`app/models/money.rb` is covered by `test/models/money_test.rb`. `bin/rails
test` with no arguments runs the whole suite. Alongside the mirrored trees,
`test/` holds `test_helper.rb` (the Rails base and the coverage setup),
`support/` for the shared helpers, `smoke_test.rb`, `seeds_test.rb`, and
`tasks/` for the rake tasks. `test/seeds_test.rb` calls
`Rails.application.load_seed` and asserts the seeded counts.

```sh
make test                                                                    # whole suite
docker compose run --rm app bin/rails test test/models/money_test.rb         # one file
docker compose run --rm app bin/rails test test/models/money_test.rb -n /percent/   # one test
```

`make test` runs `bin/rails db:test:prepare` first, so a run killed mid-way
never leaves rows behind for the next run to trip over: `test/seeds_test.rb`
and `test/smoke_test.rb` are the only tests that write outside the
transactional-fixture rollback, and the prepare step resets the schema ahead
of every run regardless of what a prior run left in `storage/test.sqlite3`.

Every test requires `test_helper` and subclasses `ActiveSupport::TestCase`,
`ActionDispatch::IntegrationTest` or `ActionView::TestCase`. A value object
test touches no database. Controller tests drive HTTP and assert on
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
the net; the buyer asks a question on the listing, the seller reads it in the
inbox, replies, and publishes the pair as an FAQ, and a third browser with no
cookie reads the question and the answer on the listing page. Time is frozen so
the payout period is the same whatever day it runs. It is part of `make test`.

## Coverage

```sh
make coverage
```

SimpleCov writes `src/coverage/` and prints the overall line coverage plus a
line per group (Models, Controllers, Helpers, Mailers). `COVERAGE_MIN` sets the
overall line minimum and fails the run below it; `make test` sets it to 100 and
is the coverage gate, so `make check` fails under 100% line coverage. `make
coverage` runs the same suite without the gate, for reading the report. The
suite stands at 748 runs and 100% line coverage.

## Linting

```sh
make lint       # rubocop, read-only
make lint-fix   # rubocop -a, the auto-fixable subset
```

RuboCop runs `rubocop-rails-omakase` (`src/.rubocop.yml`), the Rails 8 default
styling — omakase enables cops department by department rather than starting
from the community defaults, and it does not enable `Layout/LineLength`, so
this repository does not either. `make check` runs `lint` before `test`.

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
it with `stylesheet_link_tag :app`.

## JavaScript

`javascript_importmap_tags` in the three layouts serves an import map with two
entries: `application` (`src/app/javascript/application.js`, four lines, one
`import`) and `@hotwired/turbo-rails`, pinned in `config/importmap.rb` to the
`turbo.min.js` the gem ships. No bundler, no CDN, no Node.

What Turbo adds: a message thread appends the other side's reply and the nav's
Messages badge changes count without a reload, over Action Cable on Solid Cable
(`solid_cable_messages` in the same SQLite file — no Redis). The stream names
in `<turbo-cable-stream-source>` are signed, so a browser can only subscribe to
the threads and the badge the server handed it.

With JavaScript off, every page, form, link, and redirect behaves as it did
before Turbo; the thread page and the badge then change on the next load.

## Layout

```
prototype/rails/
  README.md            this file
  Dockerfile           ruby:3.3-slim + build tools + sqlite + the rails generator
  docker-compose.yml   one service: app
  docker/entrypoint.sh bundle, database, Tailwind, then the container command
  Makefile             host-side wrappers over docker compose
  docs/                architecture, feature docs (messaging, identity,
                       orders, escrow, data model, ontology), and review.md
  work/                tickets and journal
  src/                 the Rails application
    app/models/        the records, the value objects, and their behaviour
    app/controllers/   one namespace per site: shop/, seller/, admin/, auth/
    app/helpers/       status_label and the storefront header counts
    app/mailers/       MagicLinkMailer, which sends the sign-in link
    app/views/layouts/ shop, seller, admin, and the partials all three render
    config/routes.rb   /, /seller, and /admin
    test/              mirrors app/: models/, controllers/, mailers/, views/
    test/test_helper.rb SimpleCov and the Rails test base
    test/support/      the record builders and the HTTP sign-in helpers
    test/smoke_test.rb  the whole product in one walk
```

[`docs/review.md`](docs/review.md) maps every requirement in the brief to the
route and the test that prove it, and lists what is missing.

## Magic links

Passwordless on all three sites. `MagicLinkMailer.sign_in` sends the URL, and
because the container has no mailbox anyone can read, the URL is also flashed
into `flash[:debug_magic_link]`, which `layouts/_debug_alert` prints at the top
of all three layouts.

Sellers sign in at `/seller/login`, customers at `/login`, admins at
`/admin/login`; each submits an email address, then clicks the link in the
debug alert. A link lasts 15 minutes and works once. The first link for an
address creates a seller or a customer account; an admin address with no
`admins` row is refused when the link is followed.

`MagicLink.issue(email:, actor_type:)` writes the row and returns the
plaintext token beside it — only the SHA256 digest is stored — and the verify
side is `MagicLink.find_by_token`, `#usable?` and `#consume!`. A followed link
reaches `Seller.claim(email)`, `Customer.claim(email, current:)` or
`Admin.claim(email)`.

Every storefront visitor gets a `customers` row before they give an address,
carried in the signed `customer_id` cookie. Verifying an address either claims
that row (`Customer#claim_address`) or merges it into the account already
holding the address (`Customer#absorb`, which leaves a `customer_merges` row so
a stale cookie resolves forward through `Customer.from_cookie`).

```sh
MAGIC_LINK_DEBUG_ALERT=false    # default: on outside production
MAGIC_LINK_EXPIRY_MINUTES=15
```

`Auth::MagicLinksController` verifies the token; `MagicLinkSender#send_magic_link`
issues it, enqueues the mail with `deliver_later`, and sets the debug flash.
Development and test both use `delivery_method :test`, so mail accumulates in
`ActionMailer::Base.deliveries` and nothing leaves the container. The rendered
mail is at `/rails/mailers/magic_link_mailer/sign_in` in development.

`src/app/models/notification.rb` (`Notification#deliver_by_email`) is the
remaining hook, for the "Item sold", "Order shipped" and "New message"
notifications, which reach the in-app inbox today.

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

- No mail leaves the container. `MagicLinkMailer` renders and sends the sign-in
  link, and `delivery_method :test` outside production keeps it in
  `ActionMailer::Base.deliveries`; production needs the SMTP settings that are
  commented out in `config/environments/production.rb`.
  `Notification#deliver_by_email` is still empty.
- "Run weekly payout now" on the earnings page pays every seller, not the
  signed-in one. It is a debug control; `payouts:run` is the real entry point.
- A merge can leave a customer holding two carts, and the storefront shops with
  whichever holds more items.
- There is no order cancellation route.
- There is no libvips in the image, so Active Storage serves original blobs and
  a variant would raise. Seeded listings carry a generated SVG rather than a
  photograph.

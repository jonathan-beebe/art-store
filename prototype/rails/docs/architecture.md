# Art Store prototype (Rails) — system architecture

Prototype of a two-sided art marketplace: a **seller portal** (back office) and a
**customer storefront**, one Rails deployable, one SQLite file, no JavaScript
required. Every agent working in `prototype/rails/` reads this doc first and
follows the conventions in it. The PHP spike in `prototype/php/` solved the same
product; its domain decisions carry over unless stated otherwise here.

## Deployables

Question: what runs, and what talks to what?

```mermaid
flowchart LR
    subgraph docker["docker compose: app container"]
        rails["Rails 8 app (Ruby 3.3)\n/seller/* portal\n/ storefront"]
        sqlite[("SQLite\nsrc/storage/development.sqlite3")]
        rails --> sqlite
    end
    seller["Seller (browser)"] -- "HTML forms" --> rails
    customer["Customer (browser)"] -- "HTML forms" --> rails
    mail["Email delivery (future)"] -.-> rails
```

One container (`app`) holds Ruby, Bundler, the Tailwind standalone binary
(via `tailwindcss-rails`), and the SQLite file. Nothing is installed on the host.

## Layers inside the deployable

Functional core / imperative shell. Dependencies point inward only.

```mermaid
flowchart TD
    entry["Entry: config/routes.rb, config/initializers"] --> coord
    coord["Coordination: app/controllers, app/actions, lib/tasks"] --> core
    coord --> adapters
    adapters["Adapters: app/models (ActiveRecord), app/delivery, app/views"] --> core
    core["Core: app/domain/** — plain Ruby, no I/O, no clock, no random"]
```

| Layer | Lives in | Rules |
| --- | --- | --- |
| Core | `app/domain/<concept>/` | Plain Ruby: `Data.define` value objects, frozen classes, `module_function` modules. Receives time/ids as parameters. Unit tested in `test/domain/<concept>/` with no database. |
| Adapters | `app/models/`, `app/delivery/`, `app/views/` | ActiveRecord models (thin: associations, scopes, enums mapped to domain enums), the magic-link delivery port implementations, ERB views. |
| Coordination | `app/actions/<feature>/`, `app/controllers/<site>/`, `lib/tasks/` | Sequence core + adapters. Own no domain `if`s — if one appears, extract to `app/domain`. Covered by integration tests. |
| Entry | `config/routes.rb`, `config/initializers/*` | Wiring only. |

Naming follows the `naming` skill: actions are verb phrases (`PlaceOrder`,
`RunWeeklyPayout`), domain enums name states (`OrderStatus`), events are past
tense.

Action namespaces are the plural directory name — `Carts::`, `Fulfillments::`,
`Orders::`, `Listings::`, `Notifications::`, `Escrow::`, `Auth::`,
`Customers::`, `Favorites::` — not the singular the ticket originally asked
for. Rails makes every `app/*` directory a Zeitwerk root, and `app/models/cart.rb`
/ `fulfillment.rb` / `seller.rb` already define `Cart`, `Fulfillment`, `Seller`
as classes, so `app/actions/cart/` declaring `module Cart` raises `TypeError:
Cart is not a module`. The same collision makes every seller-portal controller
`class Seller::XController < Seller::BaseController` (compact form) instead of
`module Seller`; `Shop::`, `Auth::`, `Customers::`, and `Favorites::` have no
matching model and stay `module`.

## Sites

| Site | URL prefix | Session key | Theme |
| --- | --- | --- | --- |
| Seller portal | `/seller` | `session[:seller_id]` | Stock Tailwind, system font, vanilla controls, dense and tool-focused. |
| Storefront | `/` | `session[:customer_id]` + signed cookie `customer_id` for the anonymous identity | Bright, open, white space, large imagery, brand recedes. |

Controllers: `Seller::*Controller` under `app/controllers/seller/`,
`Shop::*Controller` under `app/controllers/shop/`, `Auth::*Controller` under
`app/controllers/auth/`. Layouts `app/views/layouts/seller.html.erb` and
`app/views/layouts/shop.html.erb`; both render `layouts/_debug_alert.html.erb`
which prints `flash[:debug_magic_link]`.

## Identity

- Passwordless. `magic_links` holds a hashed token, `email`, `actor_type`
  (`seller` | `customer`), `expires_at`, `consumed_at`, optional `redirect_to`.
- Delivery is a port: `MagicLinkDelivery` (duck-typed interface in
  `app/delivery/`) with `FlashMagicLinkDelivery` (prototype: flash the URL so
  the layout prints it in a debug alert) and `MailMagicLinkDelivery` (the hook
  for real email; raises `NotImplementedError`). Selected by
  `Rails.configuration.x.magic_links.delivery` (`flash` | `mail`, env
  `MAGIC_LINK_DELIVERY`).
- Customers: every visitor gets a `customers` row with `email = nil`; its id is
  stored in a signed cookie `customer_id`. Verifying an email either claims that
  row or **merges** the anonymous row into the existing verified customer
  (favorites, cart, orders, listing events, notifications re-pointed; a
  `customer_merges` row records `anonymous_customer_id -> customer_id` so stale
  cookies resolve). The merge decision is a pure function in
  `app/domain/customers`; the re-pointing is an action.
- Guest checkout = place the order as the anonymous customer, verify, then pay
  on `/orders/:id/pay`. The card is entered after verification and never stored.
- Verifying does not by itself pay the order. It moves the order from
  `pending_verification` to `awaiting_payment` (`Orders::MarkAwaitingPayment`,
  a no-op on any other status), so a guest order still has nowhere to go but
  `/orders/:id/pay`. `Shop::OrderPaymentsController` calls the action itself on
  both `show` and `create`, since `Auth::MagicLinksController` (FEAT-002)
  knows nothing about orders.

## Commerce domain

Money is integer cents (`Domain::Money`). Orders may span sellers; fulfillment
and escrow are tracked **per (order, seller)** in `fulfillments`.

```mermaid
erDiagram
    sellers ||--o{ listings : owns
    customers ||--o{ favorites : has
    customers ||--o{ carts : has
    customers ||--o{ orders : places
    listings ||--o{ listing_events : records
    listings ||--o{ cart_items : held_in
    orders ||--o{ order_items : contains
    orders ||--o{ payments : attempts
    orders ||--o{ fulfillments : split_by_seller
    sellers ||--o{ fulfillments : ships
    fulfillments ||--o{ ledger_entries : produces
    sellers ||--o{ payouts : receives
    payouts ||--o{ ledger_entries : settles
    customers ||--o{ customer_merges : merged_from
    sellers ||--o{ notifications : notifies
    customers ||--o{ notifications : notifies
```

### Listing status

`draft → for_sale → sold`, `sold → for_sale` (stock restored after a declined
card), `archived` from `draft`/`for_sale`. Search and browse
(`Shop::StorefrontController`, `Listing.for_sale`) show only `for_sale`
listings; a listing's own page (`/art/:slug`) stays reachable through `sold`
too (`Domain::Listings::ListingAvailability::ON_STOREFRONT`), so a link a
buyer already followed keeps working. `draft` and `archived` are unreachable
either way. Quantity defaults to 1; a purchase decrements and `sold` is
reached at 0.

A seller's submitted title/description/medium/dimensions/price/quantity/image
are checked by `Domain::Listings::ListingDraft.errors_for` — one pure
function, not a model validation, so the rule stays out of `Listing` (which
seeds and the storefront also load, unvalidated).

### Order status

```mermaid
stateDiagram-v2
    [*] --> pending_verification : guest places order
    [*] --> awaiting_payment : verified customer places order
    pending_verification --> awaiting_payment : email verified
    awaiting_payment --> paid : card approved
    awaiting_payment --> payment_failed : card declined
    payment_failed --> paid : retry approved
    payment_failed --> payment_failed : retry declined
    paid --> partially_shipped : one fulfillment shipped
    paid --> shipped : all fulfillments shipped
    partially_shipped --> shipped
    shipped --> delivered : all fulfillments delivered
    delivered --> [*]
    pending_verification --> cancelled
    awaiting_payment --> cancelled
    payment_failed --> cancelled
```

`cancelled` has no route to it from either UI in this prototype — the
transition exists in `Domain::Orders::OrderStatus::TRANSITIONS`, verified by
its unit test, but no action calls it.

### Fulfillment status (per order × seller)

`awaiting_shipment → shipped → delivered`. Seller marks shipped (carrier +
tracking). Customer confirms delivery from the order page. A seller's order
**is a fulfillment** — `seller_order` (`/seller/orders/:id`) takes a
`fulfillments.id`, since that is the slice of a customer's order the seller
owns.

### Escrow and payouts

- `ledger_entries.entry_type` (not `type` — that column is reserved for Active
  Record's single-table inheritance, same reason `listing_events.event_type`
  isn't `type`) is `held` (+net, written when the order pays), `released`
  (+net, written when the fulfillment is delivered), or `paid_out` (−amount,
  written when included in a payout). `Domain::Escrow::LedgerBalance.from`
  folds a seller's entries: `held = held_total − released_total`; `available =
  released_total + paid_out_total` (the `paid_out` entries are already
  negative, so this nets down as money leaves); `paid_out = −paid_out_total`
  (a positive lifetime figure).
- Platform fee: 10% of the fulfillment subtotal (`Domain::Escrow::Fee`),
  computed once at order placement (`Orders::PlaceOrder`) and stored on the
  `fulfillments` row (`fee_cents`, `net_cents`). Net = subtotal − fee.
  `Orders::FinalizeOrder` (hold) and `Fulfillments::ConfirmDelivered` (release)
  move `fulfillment.net` through escrow rather than recomputing it.
- Payout period = Monday–Sunday. `bin/rails payouts:run[AS_OF]` creates one
  `payouts` row per seller for released-not-paid amounts as of the most
  recently completed week. Period math is pure (`Domain::Escrow::PayoutPeriod`).
  The seller portal exposes a debug "Run weekly payout now" button.

### Fake payment

`Domain::Payments::FakeCard.decide(number)`:

| Number | Decision |
| --- | --- |
| `4242 4242 4242 4242` | approved |
| `4000 0000 0000 0002` | declined: generic decline |
| `4000 0000 0000 9995` | declined: insufficient funds |
| anything else | declined: invalid card number |

Spaces and dashes are ignored. Only the last four digits are stored.

### Notifications

`notifications` rows (nullable `seller_id` / `customer_id`, subject, body, url,
read_at) shown in each site's header. Seller receives "Item sold" on paid;
customer receives "Order shipped" on shipped. `Notify#deliver_by_email` is the
email hook.

## Testing

- Minitest (stock Rails). Every test lives under `test/`, mirroring the tree it
  covers: `app/domain/money.rb` → `test/domain/money_test.rb`,
  `app/actions/orders/place_order.rb` → `test/actions/orders/place_order_test.rb`,
  `lib/tasks/payouts.rake` → `test/tasks/payouts_test.rb`. `bin/rails test`
  with no arguments runs the whole suite. `test/test_helper.rb` is the Rails
  base and starts SimpleCov.
- Every test declares itself with `test "..." do` and subclasses
  `ActiveSupport::TestCase`, `ActionDispatch::IntegrationTest` or
  `ActionView::TestCase`. There is no intermediate base class.
- `test/test_helper.rb` requires `test/support/**/*.rb` and mixes it in:
  `TestRecords` (the record builders and the card numbers) into
  `ActiveSupport::TestCase`, `IntegrationHelpers` (sign-in over HTTP, the
  cookie readers, the seller-portal order state) into
  `ActionDispatch::IntegrationTest`. There are no fixture files — `fixtures
  :all` loads one shared directory for every suite, so each test builds the
  rows it asks about.
- Core tests (`test/domain/**`) exercise the file under test — no database, no
  doubles.
- Coordination tests (controllers, actions, tasks) run against the test SQLite
  database; they drive HTTP and assert on rendered HTML and DB state.
- Coverage via SimpleCov: `bin/rails test` writes `coverage/` and prints a
  per-group summary (Domain, Actions, Controllers, Models). `COVERAGE_MIN` is
  one global line-coverage minimum (`make coverage` sets it to 80) — SimpleCov
  reports the Domain group's percentage but nothing enforces a higher
  threshold on it specifically; it has stayed near 100% in practice because
  the core is small and pure.
- TDD: failing test, make it pass, refactor. Feature tickets are done
  when their flow has an integration test that walks it end to end.

## Repository layout

```
prototype/rails/
  README.md            how to run, serve, test
  docker-compose.yml   one service: app
  Dockerfile           ruby:3.3-slim + sqlite + build tools
  docker/              entrypoint.sh
  Makefile             host-side wrappers over docker compose
  docs/                architecture + feature docs (this folder)
  work/                tickets and journal (orchestration only)
  src/                 the Rails application
```

## Mapping the project skills onto this stack

| Skill says | Here it means |
| --- | --- |
| `npm run test:run -- <pattern>` | `docker compose run --rm app bin/rails test <path>` |
| Vitest unit test | Minitest `ActiveSupport::TestCase` under `test/domain/` |
| React Testing Library integration test | `ActionDispatch::IntegrationTest` under `test/controllers/` |
| `src/` | `prototype/rails/src/` |

---
id: FEAT-003
type: feature
status: resolved
created: 
---

# FEAT-003: Commerce domain core, schema, and order lifecycle actions

## Problem
Listings, carts, orders, payments, fulfillment, escrow, and payouts have no schema, no domain rules, and no actions. The seller portal (FEAT-004) and storefront (FEAT-005) need these as a tested core they call into.

## Goal
The entire order lifecycle — place, pay with a fake card, notify seller, ship, deliver, hold and release escrow, pay out weekly — runs and is proven by tests before any UI exists.

## Outcome
- Migrations (timestamps `20260822000201`…) and thin ActiveRecord models for `listings`, `listing_events`, `favorites`, `carts`, `cart_items`, `orders`, `order_items`, `payments`, `fulfillments`, `ledger_entries`, `payouts`, `notifications`.
- Pure core under `app/domain/`, each with a sidecar test that runs without Rails: `Domain::Listings::ListingStatus` and `ListingStock`, `Domain::Payments::FakeCard` / `CardDecision` / `DeclineReason`, `Domain::Orders::OrderStatus` (transitions + `from_fulfillments` roll-up), `FulfillmentStatus`, `Purchaser`, `ShippingAddress`, `Domain::Cart::CartTotals` / `CartLine` / `CartQuantity`, `Domain::Escrow::Fee` (10%), `LedgerBalance`, `PayoutPeriod` (Mon–Sun), `Domain::Notifications::NotificationMessage`.
- Actions under `app/actions/`, each with a sidecar test against the test database: `Cart::AddToCart`, `Cart::RemoveFromCart`, `Orders::PlaceOrder` (cart → order with per-seller fulfillments, stock decremented, cart emptied; `pending_verification` when the purchaser is unverified, else `awaiting_payment`), `Orders::FinalizeOrder` (charge via `FakeCard`, write `payments`, `paid` or `payment_failed`; on paid write `held` ledger entries and a seller "Item sold" notification; on failed restore stock), `Fulfillment::MarkShipped` (rolls up order status, customer "Order shipped" notification), `Fulfillment::ConfirmDelivered` (rolls up, writes `released`), `Escrow::RunWeeklyPayout` (one payout per seller for released-not-paid > 0 as of a date, `paid_out` entries dated at period end, idempotent per period), `Listings::RecordListingEvent`, `Notifications::Notify` (with a `deliver_by_email` hook).
- `bin/rails payouts:run[AS_OF]` rake task calls `RunWeeklyPayout`.
- An end-to-end test walks seller + listing → cart → place → finalize with 4242 → ship → confirm delivery → payout run → ledger balances and payout rows match numerically. A second covers a declined card and a retry.

## Why it matters
This is the functional core; both UIs are shells over these actions.

## Discovery notes
Read `docs/architecture.md` → Commerce domain. The PHP spike's `app/Domain/**` and `app/Actions/**` in `prototype/php/` plus `docs/orders.md` and `docs/escrow.md` are a worked reference of the same rules; port the behavior and the test cases, in idiomatic Ruby (`Data.define`, frozen value objects, `module_function`). Enums as modules of frozen string constants with a `TRANSITIONS` hash and `can_transition?(from, to)`; ActiveRecord `enum` maps to the same strings.
- FEAT-002 creates `sellers`, `customers`, `customer_merges`, `magic_links` and the `Seller`/`Customer` models in parallel. Reference their tables in foreign keys; do not create them. If they are absent when you run tests, use a temporary schema loaded only in your test base (`test/commerce_test_case.rb`) and never overwrite their files.
- Keep time out of the core: actions take `now:` and pass it into domain functions.
- Listing fields: seller_id, title, slug, description, price_cents, quantity, status, medium, dimensions, image (Active Storage `has_one_attached :image`).

## Working

### Decisions

- **Stock is restored when a card is declined.** `PlaceOrder` takes the stock;
  a decline hands it back (`sold -> for_sale` when the listing had reached
  zero) so the listing returns to the storefront while the customer finds
  another card, and a retry claims it again. The rule is one pure function,
  `Domain::Orders::OrderStock.change(from:, to:)`, which reads "an order holds
  its stock for as long as it can still be paid" — payment_failed and
  cancelled hold nothing. It answers all four cases, including a retry that is
  declined again (no change, so no double restock).
- **`awaiting_payment` is a status a guest order passes through**, per the
  order-status diagram in `docs/architecture.md`:
  `pending_verification -> awaiting_payment -> paid`. A guest cannot reach
  `paid` directly, so `FinalizeOrder` refuses an unverified order. The
  transition is `Domain::Orders::OrderStatus.after_verification(status)`,
  driven by the action `Orders::MarkAwaitingPayment` — **FEAT-005's
  verify-then-pay flow has to call it** after the magic link is consumed. This
  is the one place the Rails design departs from the PHP spike, where
  `pending_verification -> paid` was legal.
- **`payment_failed -> payment_failed` is legal.** The architecture diagram has
  it; the spike did not, so a second decline threw there.
- **Enums are modules of frozen strings** with `ALL`, `TRANSITIONS`,
  `can_transition?(from, to)` and `transition(from, to)`, which returns the
  next status or raises `Domain::TransitionError`. An unknown status has no
  transition list, so it can go nowhere and the error names it. ActiveRecord
  maps to the same strings with `enum :status, ALL.index_by(&:to_sym)`, which
  also gives the scopes and predicates the two UIs need.
- **Two error types.** `Domain::TransitionError` for a status move the table
  refuses; `ArgumentError` for everything else (an empty cart, a sold-out
  listing, a sale larger than the stock). Value errors are about the arguments
  handed in, which is what `ArgumentError` means.
- **`Domain::Orders::PaymentAttempt` keeps the domain `if`s out of
  `FinalizeOrder`.** One `Data` value carries the order status, the payments
  row, the stock change, and `finalized_at`; `#settled(fulfillments)` returns
  an empty list unless the charge was approved, so the action loops instead of
  branching. The action holds no conditional.
- **`PayoutPeriod` is two `Date`s** (`first_day`, `last_day`) plus `ends_at`,
  the last second of the last day. The `paid_out` entry is dated at `ends_at`
  rather than at the moment the run happens, which is what makes a second run
  of the same period pay nothing: the money already nets to zero inside
  `occurred_at <= ends_at`.
- **Actions are plain classes with an instance `#call`,** called as
  `Orders::PlaceOrder.new.call(...)`. Collaborators arrive through the
  constructor with a real default, so a test can pass a fake without a
  container. No base class — nothing was shared enough to earn one.
- **`ledger_entries.entry_type` and `listing_events.event_type`,** not `type`:
  `type` is ActiveRecord's STI column, and renaming the column beats disabling
  inheritance on two models.
- **Model sidecars only where a model does something** — `Cart#lines`,
  `Order#total`, `Payout#amount`. The rest is associations and enums, covered
  through the action tests.

### Deviations from the ticket

- **`Carts::` and `Fulfillments::`, not `Cart::` and `Fulfillment::`.** Rails
  makes every `app/*` directory a Zeitwerk root, so `app/actions/cart/` asks
  for a `Cart` namespace while `app/models/cart.rb` defines `Cart` as a class;
  the file's own `module Cart` then raises `TypeError: Cart is not a module`.
  Plural directories also match `Orders::`, `Listings::`, `Notifications::`.
- **`Orders::RollUpOrderStatus` and `Orders::MarkAwaitingPayment` were added.**
  The roll-up is shared by both fulfillment actions; the second is the
  `pending_verification -> awaiting_payment` step the status diagram needs.
- **No `Domain::Reports`.** Nothing in this ticket's outcome reads from it;
  FEAT-004 and FEAT-005 own their own reporting.
- **No temporary `sellers` / `customers` scaffolding in
  `test/commerce_test_case.rb`.** FEAT-002's migrations and models had landed
  before the first test run, so the base creates real rows.

### Notes for the tickets that follow

- `db/schema.rb` carries FEAT-002's `sellers`, `customers`, `customer_merges`,
  and `magic_links` alongside the commerce tables. Active Storage's three
  tables are migration `20260822000200`, generated here.
- `Listings::RecordListingEvent` writes every `listing_events` row;
  `AddToCart` already records `cart_add`. Views and favorites are the
  storefront's to record.
- `Notifications::Notify#deliver_by_email` is the mail hook and does nothing.

### Verified

- `make test`: 371 runs, 657 assertions, 0 failures. 98.80% overall line
  coverage, Domain 99.74% (every `app/domain` file this ticket added is 100%),
  Actions 100%, Models 96.81%. `COVERAGE_MIN=80` passes.
- FEAT-003's own files: 232 runs, 414 assertions — 131 core, 95 action, 4
  model, 2 rake task.
- Core tests run with no Rails boot, e.g.
  `docker compose run --rm app ruby -Iapp app/domain/orders/payment_attempt_test.rb`.
- `bin/rails zeitwerk:check`: all is good.
- `docker compose run --rm app bin/rails "payouts:run[2026-08-24]"` prints the
  period and the sellers paid.
- The lifecycle test walks two sellers from cart to payout and checks the
  ledger arithmetic at every step; a second walks a declined card, the stock
  coming back, and a retry that completes the order.

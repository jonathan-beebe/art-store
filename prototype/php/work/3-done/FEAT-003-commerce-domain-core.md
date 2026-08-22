---
id: FEAT-003
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-003: Commerce domain core, schema, and order lifecycle actions

## Problem
Listings, carts, orders, payments, fulfillment, escrow, and payouts have no schema, no domain rules, and no actions. The seller portal (FEAT-004) and storefront (FEAT-005) both need these to exist as a tested core they can call into rather than each inventing its own.

## Goal
The entire order lifecycle — place, pay with a fake card, notify seller, ship, deliver, hold and release escrow, pay out weekly — runs and is proven by tests before any UI exists.

## Outcome
- Migrations and thin Eloquent models exist for `listings`, `listing_events`, `favorites`, `carts`, `cart_items`, `orders`, `order_items`, `payments`, `fulfillments`, `ledger_entries`, `payouts`, `notifications` (timestamps `2026_08_22_0002xx`).
- Pure core in `app/Domain/**`, each with a sidecar test: `Money` (from FEAT-001), `Listings\ListingStatus` transitions, `Payments\FakeCard` decisions per the table in `docs/architecture.md`, `Orders\OrderStatus` transitions and `OrderStatus::fromFulfillments(...)` roll-up, `Orders\FulfillmentStatus` transitions, `Cart\CartTotals`, `Escrow\Fee` (10%), `Escrow\LedgerBalance` (held / available / paid out from a list of entries), `Escrow\PayoutPeriod` (Monday–Sunday period containing or preceding a date).
- Actions in `app/Actions/<Feature>/`, each with a sidecar feature test against in-memory SQLite: `Cart\AddToCart`, `Cart\RemoveFromCart`, `Orders\PlaceOrder` (cart → order with per-seller fulfillments, stock decremented, cart emptied; status `pending_verification` when the customer is unverified, otherwise proceeds to charge), `Orders\FinalizeOrder` (charge via `FakeCard`, write `payments`, set `paid` or `payment_failed`, on paid write `held` ledger entries and a seller "Item sold" notification), `Fulfillment\MarkShipped` (carrier + tracking, rolls up order status, customer "Order shipped" notification), `Fulfillment\ConfirmDelivered` (rolls up, writes `released` ledger entry), `Escrow\RunWeeklyPayout` (one payout per seller for released-not-paid amounts as of a date, writes `paid_out` entries), `Listings\RecordListingEvent` (view / favorite / unfavorite / cart_add).
- `php artisan payouts:run {--as-of=}` calls `RunWeeklyPayout`.
- An end-to-end feature test walks: seller + listing → customer adds to cart → places order → finalizes with 4242 → seller ships → customer confirms → payout run → ledger balances and payout rows match expectations. A second test covers a declined card and a retry.

## Why it matters
This is the functional core of the product. Both UIs are shells over these actions; testing the lifecycle here means the UI tickets only test wiring.

## Discovery notes
Read `docs/architecture.md` → Commerce domain (statuses, escrow, fake card).
- FEAT-002 is creating `sellers`, `customers`, `customer_merges`, `magic_links` in parallel (timestamps `2026_08_22_0001xx`) and the `Seller` / `Customer` models. Reference those tables by name in foreign keys but do not create them. If the models are not present yet when you start, create minimal `Seller`/`Customer` models only if missing, and coordinate by not overwriting FEAT-002's versions (check the file exists first).
- Keep time and randomness out of the core: actions pass `now()` into domain functions.
- Listing fields: seller_id, title, slug, description, price_cents, quantity, status, image_path nullable, medium (e.g. oil, print, ceramic), dimensions text nullable, timestamps. A listing with quantity 0 is `sold`.
- Order fields: customer_id, email, status, shipping name/address lines, subtotal_cents, total_cents, placed_at, finalized_at. Order items snapshot title and price. Fulfillments: order_id, seller_id, status, carrier, tracking_number, shipped_at, delivered_at, subtotal_cents, fee_cents, net_cents.
- Ledger entries: seller_id, fulfillment_id nullable, payout_id nullable, type (`held`, `released`, `paid_out`), amount_cents (signed), occurred_at.
- Notifications: recipient_type, recipient_id, subject, body, url, read_at. Keep a `Notify` action with the same port shape as `MagicLinkDelivery` so email can be added behind it later.
- Stock: decrement on `PlaceOrder`; restore on `payment_failed`? Decide and document in the action's test — restoring keeps the storefront honest for one-of-a-kind art.

## Working

### Decisions

- **`awaiting_payment` order status added.** `docs/architecture.md` shows a
  verified customer going straight from placement to `paid`, which leaves
  `PlaceOrder` no honest status to write before the charge. Reusing
  `pending_verification` for a verified customer would make the column lie.
  `OrderStatus::forPlacement(Purchaser)` returns `pending_verification` when the
  address is unverified and `awaiting_payment` otherwise; both transition to
  `paid`, `payment_failed`, and `cancelled`.
- **Stock is restored when a charge is declined.** `PlaceOrder` takes the stock,
  `FinalizeOrder` gives it back on `payment_failed`, and a retry
  (`FinalizeOrder` again on a `payment_failed` order) takes it a second time.
  One-of-a-kind art sitting behind a dead checkout is worse than the small race
  a retry can lose. `ListingStatus` therefore allows `sold -> for_sale`.
- **`OrderStatus::fromFulfillments` counts a delivered fulfillment as departed.**
  The stated rule ("any shipped -> partially_shipped") leaves
  `[delivered, awaiting_shipment]` rolling up to `paid`. The roll-up asks
  `FulfillmentStatus::hasLeftTheStudio()` instead, so a part-delivered order is
  `partially_shipped`.
- **`notifications` carries nullable `seller_id` and `customer_id` rather than
  `recipient_type` + `recipient_id`.** Both recipients are real tables, so
  separate columns keep the foreign keys real, match the ER diagram, and let
  FEAT-002's `CustomerOwnedTables` re-point rows by `customer_id` on an
  anonymous merge. `RecipientType` stays as the domain-facing name and
  `Notification::recipientColumn()` maps it.
- **`payments` holds a row per attempt.** A retry needs its own record; the
  latest row is the order's current payment.
- **`ledger_entries.amount_cents` is signed**: `held` and `released` positive,
  `paid_out` negative. `LedgerBalance` reads
  held = held − released, available = released + paid_out, paidOut = −paid_out.
- **A payout's `paid_out` entry is dated at the period end, not the run time.**
  `RunWeeklyPayout` reads entries with `occurred_at <= period end`; dating the
  settlement inside the period it settles is what makes a second run of the same
  period a no-op. `payouts` also carries `unique(seller_id, period_start)`.
- **Actions take the customer as `Orders\Purchaser`, not the `Customer` model.**
  Keeps the order actions off FEAT-002's model and keeps the verification rule
  (`isEmailVerified()`) in the core.
- **`Notify` takes no `now`.** The row's `created_at` is the notification's
  time; nothing branches on it. The email hook is `Notify::deliverByEmail()`.
- **Order total equals the item subtotal.** No shipping or tax in the prototype.
- **`carts.customer_id` is not unique.** An anonymous merge can re-point a
  second cart onto a customer; `AddToCart` works against whichever cart it is
  handed.

### Deviations from the conventions

- `app/Actions/Orders/OrderLifecycleTest.php` has no production sidecar. The
  lifecycle is the composition of the actions around it, and `phpunit.xml` only
  scans `app/` and `routes/`, so putting it beside the order actions avoided
  editing a file FEAT-002 also owns.
- Models whose only members are relations and casts (`Favorite`, `ListingEvent`,
  `OrderItem`, `Payment`, `Payout`, `Order`, `Fulfillment`, `Cart`, `CartItem`,
  `LedgerEntry`, `Notification`) have no sidecar test; the action tests drive
  them. `Listing` has one because its `forSale` scope has no caller yet.
- Only `ListingFactory` was added. Orders, carts, and fulfillments are built by
  running the real actions, which is what the tests need to assert against.

### Parallel work with FEAT-002

- `sellers`, `customers`, `App\Models\Seller`, `App\Models\Customer`,
  `SellerFactory`, and `CustomerFactory` all existed by the time the schema
  landed, so no temporary shims were created and none were needed.
- `tests/CommerceTestCase.php` is the shared base for the commerce action tests
  (in-memory database plus seller/customer/listing/cart/order helpers).
- Full suite green at hand-off: 264 tests, 581 assertions.

---
id: FEAT-003
type: feature
status: open
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

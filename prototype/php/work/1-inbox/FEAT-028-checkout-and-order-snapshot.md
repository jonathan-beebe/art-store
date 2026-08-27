---
id: FEAT-028
type: feature
status: open
created: 2026-08-26
---

# FEAT-028: Checkout and order snapshot

## Problem
FEAT-027 gives a buyer a cart line with a live-resolved configuration and price. Nothing yet freezes that configuration at the moment of purchase the way `order_items` today freezes `title`/`unit_price_cents` against later listing edits, and nothing yet claims a serialized unit or decrements a variant's stock at placement. Without a frozen snapshot, a seller edit to an axis's surcharge after purchase would silently rewrite a past order's displayed price and configuration — the same "past orders left intact" guarantee the plain listing case already gets.

## Goal
Placing an order freezes a configured cart line's price breakdown, configuration, and answers exactly as computed at that moment, claims any serialized unit and decrements variant stock inside the placement transaction, and every surface that shows an order item (customer, seller, admin) renders that frozen data.

## Outcome
- [ ] `order_items` migration adds nullable `variant_id`, nullable `unit_id`, `configuration_json`, `answers_json`, `price_breakdown_json` — the same snapshot doctrine as the existing `title`/`unit_price_cents` columns: written once at placement, never re-derived from current listing state afterward.
- [ ] `order.place` (the placement action) re-resolves each configured cart line's price/availability at the moment of placement (not trusting a stale cart-time value), writes the frozen columns, and blocks any line that is no longer available — reusing the existing blocked-lines checkout refusal path (the one `CheckoutController::place` already routes back to the cart with the unavailable line named) rather than adding a second refusal shape.
- [ ] Inside the same transaction: a serialized line's chosen unit flips `available → sold`; a non-serialized configured line's `variant.quantity` decrements, mirroring the existing `listing.quantity` decrement-at-placement rule.
- [ ] Cancel/decline restores exactly what placement claimed: a claimed unit flips back to `available`, a decremented variant quantity is restored — mirroring the existing stock-restore rules for plain listings (`docs/alignment.md` §4.1's fulfillment decline / admin refund restore semantics, and cancel's stock restore).
- [ ] No cart-time reservation — a unit or variant quantity is untouched until `order.place` runs inside its transaction; the design's dropped `'reserved'` unit state is not implemented.
- [ ] Customer order page, seller fulfillment view, and admin order detail all render the frozen `configuration_json`/`price_breakdown_json`/`answers_json` for a configured line, alongside the existing snapshot fields for a legacy line — one line-item partial handling both shapes, not two parallel renderers.
- [ ] `make smoke` (the end-to-end walk) gains a configured purchase: at least one archetype from FEAT-025's seeds goes through browse → configure → cart → checkout → pay → ship, asserting the frozen breakdown appears unchanged on the customer, seller, and admin views.
- [ ] Tests: placement blocking an unavailable configured line, unit claim/restore on cancel and decline, variant quantity decrement/restore, a seller edit to an axis surcharge after purchase leaving the placed order's snapshot unchanged; sidecar test per new/changed class; `make check` green; coverage 100%.
- [ ] `prototype/php/work/journal.md` updated: FEAT-028 defined/started/done lines.

## Why it matters
This is the point in the lifecycle where "the price on screen is the price at checkout" becomes a durable fact rather than a live computation — and where a serialized unit or a scarce variant actually leaves inventory, closing the loop the earlier three tickets set up without touching stock.

## Discovery notes
- Read `docs/alignment.md` §4 in full before starting: the order/fulfillment state machines, the three refund timings in §4.2 (held/released/refunded against the ledger), and §4.3's sad-path list — a configured line's stock-restore rules must satisfy the same table, just extended to units/variants instead of only `listings.quantity`.
- `prototype/php/docs/architecture.md` §"Refusals" and §"The clock": `CheckoutController::place` is named there as the one route that overrides the destination on a `DomainRuleViolation`, sending the shopper back to the cart with the unavailable line marked — extend that same mechanism for a configured line, do not add a second one. Actions take `DateTimeImmutable $now` as their last parameter; no action calls `now()`.
- `app/Actions/Orders/` (the placement/finalize/cancel/decline actions) and `app/Models/Listing.php`'s `sell()`/`restock()` methods are the existing shapes for stock movement — `Variant`/`Unit` need the equivalent methods (e.g. `Unit::sell()`, `Unit::restock()`, `Variant::decrementQuantity()`, `Variant::restoreQuantity()`), following the same "model method applies a decision the core made and writes the row" pattern from the architecture doc's Adapters row.
- Log events stay within the closed §2.3 vocabulary: this ticket rides the existing `order.place`, `order.cancel`, `fulfillment.decline`, `refund.issue` events — no new event name for a unit claim or variant decrement.
- Risk: `FulfillmentPolicy`/`OrderPolicy` ownership checks (wrong seller/customer → 404) already exist; make sure the new render partials for configured lines don't leak another seller's variant/unit data through an id a policy doesn't check (the line-item partial reads through the order/fulfillment it's already scoped to, never an independently-loaded variant/unit by raw id).
- Money stays integer cents throughout; `price_breakdown_json` is the same itemized-line shape (`[{label, cents}]`) FEAT-027 computes live, just persisted.

## Related work
- FEAT-025 (data model; `Variant`/`Unit` models and pricing this ticket freezes)
- FEAT-026 (seller configurator UI — a post-purchase axis edit is what this ticket's snapshot test guards against)
- FEAT-027 (buyer configurator + cart; the live computation this ticket freezes)
- `__local__/item-configuration/etsy-product-configuration.md`
- `__local__/item-configuration/etsy-product-configuration-design-doc.md`
- `docs/alignment.md` §4 (order/fulfillment lifecycle), §2 (logging)

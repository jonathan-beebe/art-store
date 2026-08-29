---
id: BUG-007
type: bug
status: resolved
created: 2026-08-27
---

# BUG-007: A priced question on a choice-free listing never charges the buyer

## Problem
`CartItem::isConfigured()` (prototype/php/src/app/Models/CartItem.php) answers
`variant_id !== null`, and `toLine()` / `currentBreakdown()` skip configuration
pricing entirely when it is false. A listing with no option axes never assigns
a variant, so a priced question's answer (a modifier with
`add_on_price_cents`, or a measurement rate) is collected from the buyer but
never priced: the cart line and the charged total omit it.
`ConfigurationPricer::price()` handles modifier deltas correctly; the gate in
front of it is what skips them.

## Goal
A buyer pays the full configured price whether or not the listing offers
choices.

## Outcome
A listing with zero option axes and a priced question produces a cart line,
breakdown, and order total that include the answer's price, identical in
shape to the same question on a listing that has choices.

## Why it matters
The seller quietly undercharges on every such order (story B2's mug seller,
the moment they remove their last choice), and the itemized-breakdown promise
— the price on screen is the price at checkout — breaks in the opposite
direction: work is delivered unpaid.

## Discovery notes
Found by DSGN-001's story sweep (C6/C7 test authoring); those tests were
written against listings with an axis to exercise the working path. The
pricing skip sits in the `isConfigured()` gate, and the reporter observed the
root fix likely reaches `StockMovement`, `PlaceableLineBuilder`, and
`PlaceOrder` — advisory, the maker decides shape. A failing test is cheap:
axis-free listing, one priced question, add to cart, assert the breakdown.

## Related work
- DSGN-001 (sweep that surfaced it)
- prototype/php/docs/item-configurator.md §3 (price resolution)

## Working

Root cause confirmed narrower than the ticket's hypothesis:
`CartItem::currentBreakdown()` already prices an axis-free line's modifier
answers correctly — it calls `ConfigurationPricer::price()` unconditionally,
passing `null`/`[]` for variant/selected-options when unconfigured. The gate
that drops the answer's price sits one level up, in the three places that
chose not to call `currentBreakdown()` at all for an unconfigured line:
`CartItem::toLine()` (returned a flat `price * quantity` line), `PlaceOrder::snapshotItems()`
(stored a `null` breakdown and a bare `listing.price_cents` as the frozen
unit price), and `OrderItem::lineTotal()` (fell back to `unit_price_cents *
quantity` for any `variant_id === null` row rather than its frozen
breakdown).

`StockMovement` and `PlaceableLineBuilder` needed no change — both only ever
read variant/unit rows for stock and availability, never price, so their
`isConfigured()` branch was already correct.

Changed:
- `app/Models/CartItem.php` — `toLine()` always resolves through
  `currentBreakdown()`.
- `app/Actions/Orders/PlaceOrder.php` — `snapshotItems()` always computes and
  freezes a breakdown, for every line.
- `app/Models/OrderItem.php` — `lineTotal()` now keys off whether a frozen
  breakdown exists (`price_breakdown_json !== null`) rather than
  `isConfigured()`, so a legacy line placed before this fix (no breakdown
  ever frozen) still falls back to `unit_price_cents * quantity`.

Tests added:
- `App\Models\CartItemTest`: "it prices a text modifiers flat answer on an
  axis-free listing, matching a configured lines shape", "it prices a
  measurement modifiers rated answer on an axis-free listing"
- `App\Actions\Orders\PlaceOrderTest`: "it freezes an axis-free lines
  modifier answer price at placement, matching a configured lines shape"

Full suite: 2706 passed (7729 assertions).

Refactor note (not done, out of scope): `CartItem` and `OrderItem` each carry
two independent boolean axes under one name — "resolves to a variant"
(`isConfigured()`) and "has a priced breakdown to total" — and every call
site has to know which one it means. A second predicate (or renaming
`isConfigured()` to what it actually answers, e.g. `hasVariant()`) would make
the distinction visible at the call site instead of relying on each caller
picking the right check.

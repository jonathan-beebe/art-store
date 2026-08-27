---
id: BUG-007
type: bug
status: open
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

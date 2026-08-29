---
id: BUG-012
type: bug
status: resolved
created: 2026-08-27
---

# BUG-012: A standalone choice's selected option hides its price in the buyer dropdown

## Problem
On a choice priced "each option on its own", the buyer dropdown renders
the selected option's label bare while every other option carries its
absolute price — a three-size print shows "8x10" selected and "11x14
($24.00)" / "16x20 ($34.00)" beside it
(prototype/php/src/resources/views/shop/partials/configurator.blade.php
and resources/views/components/seller/buyer-view.blade.php render the
selected standalone option without its price). The first/default option
therefore never shows a price in the closed control or the open list.

## Goal
Every option on a standalone choice states its price where it is picked.

## Outcome
All options on a standalone-priced choice display their price in the
buyer dropdown, the selected one included, on the shop page and in the
seller buyer-view panel alike.

## Why it matters
The selected option is the one the buyer is about to pay for; it is the
one whose price the dropdown omits. A buyer scanning the list reads
prices for every size except the size in the box.

## Discovery notes
Reported live against a three-option standalone choice. The bare-when-
selected rendering came from the DSGN-002 canvas's Main artboard; the
reporter overrules it. The itemized price panel already shows the
selected option's absolute line, so the fix is presentational and local
to the two views; A9/A10's story tests assert deltas at the point of
choice and may want a sharpened assertion here.

## Related work
- prototype/php/work/3-done/DSGN-002-retire-legacy-form-unify-editor-into-rows.md

## Working
Removed the `@unless($option['selected'])` guard around the standalone
price suffix in both views, so every standalone option renders
`label ($price)` regardless of selection. Add-on delta rendering is
unchanged.

Files changed:
- `src/resources/views/shop/partials/configurator.blade.php`
- `src/resources/views/components/seller/buyer-view.blade.php`
- `src/app/Http/Controllers/Shop/ListingControllerTest.php` — sharpened
  `it shows a standalone size's absolute prices and an absolute-first
  breakdown` to assert both the selected and non-selected option's
  price
- `src/app/View/Components/Seller/BuyerViewTest.php` — added `it shows
  a standalone option's own absolute price whether or not it is
  selected`

Full suite: 2719 passed (7757 assertions).

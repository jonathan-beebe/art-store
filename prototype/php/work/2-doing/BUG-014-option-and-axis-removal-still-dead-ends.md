---
id: BUG-014
type: bug
status: open
created: 2026-08-28
---

# BUG-014: Removing a listing's options or variants still dead-ends

## Problem
Reported live on 2026-08-28, after BUG-008 (commit dce13e5) shipped the
variant destroy route, row control, and `forVariant` guard: tapping Remove on
an option value answers "This option value is selected by a variant; remove
that variant first." and tapping Remove on the axis answers "This axis has a
variant built from one of its values; remove or reassign that variant first."
The reporter states they cannot remove the listing's options or variants —
the variant-removal path BUG-008 added is not getting them to "removed."

## Goal
A seller can actually remove an option value, an axis, and the variants in
the way, end to end, through the live UI.

## Outcome
On a real listing in the running app, the seller removes the blocking
variant(s) and then the option value and axis, with each control they need
visible and working. If the block is a live-data reference or a message not
pointing at the working control, that specific defect is fixed and covered by
a test.

## Why it matters
BUG-008's fix has an end-to-end feature test proving the path, yet the
reporter hit the same dead end in the live app the next day — either the
shipped control fails or is invisible in practice, or a reference the tests
don't seed (cart or order rows) re-blocks the path, or the guard messages
send the seller to a screen where the needed control cannot be found.

## Discovery notes
Root-cause candidates to check, in order:
1. `ConfiguratorDeletionGuard::forVariant` refuses when ANY `CartItem` or
   `OrderItem` references the variant. Seeded/demo listings and any listing
   with sales history hold order rows forever — that would make every such
   variant permanently undeletable, re-creating the BUG-008 dead end for any
   listing that has ever sold. Decide what the guard should actually protect
   (live carts and open orders vs. all history — order items freeze their
   own snapshot in `price_breakdown_json`, so a historical reference may not
   need the live row).
2. The variant Remove control shipped on the Combinations & stock screen,
   but the guard messages on the Choices screen say "remove that variant
   first" without naming where — verify the control renders and works on the
   reporter's listing shape, and whether the message should point at the
   screen.
3. Reproduce in the running app (`make up`, port 8000), not only in tests.

## Related work
- BUG-008 (prototype/php/work/3-done/) — the destroy path this report says
  still dead-ends

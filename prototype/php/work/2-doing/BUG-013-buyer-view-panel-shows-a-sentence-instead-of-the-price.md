---
id: BUG-013
type: bug
status: open
created: 2026-08-27
---

# BUG-013: The buyer-view panel shows a sentence instead of the simple listing's price

## Problem
For a listing with no configurator (no choices, questions, pieces, or
discounts), the seller's "What buyers see" panel
(resources/views/components/seller/buyer-view.blade.php:10-11) renders only
the sentence "Nothing here yet for a buyer to configure — this listing adds
straight to cart." The actual buyer page for that same listing
(resources/views/shop/listing.blade.php) shows the item's title, its price,
and an Add to cart button — the panel claims to show what buyers see and
then shows none of it. Reported immediately after creating an item through
the new guided create flow.

## Goal
The buyer-view panel always shows what a buyer actually sees, configurator
or none.

## Outcome
A seller viewing the panel for an unconfigured listing sees the item as a
buyer would — its title, its one price, and the add-to-cart presentation —
instead of an explanatory sentence; configured listings keep their current
panel rendering.

## Why it matters
The panel is the redesign's core reassurance (human-confirmed must-keep);
for the simplest listings — the first thing every guided-create seller
makes — it currently reassures with a caveat instead of a preview, and a
ramp-1 seller never sees their price the way a buyer will.

## Discovery notes
The shop page's unconfigured rendering (shop/listing.blade.php: price line
+ Add to cart form) is the shape to mirror, read-only/inert like the rest
of the panel; made-to-order and sold-out states should render honestly (the
stock label support from DSGN-003 exists). Reproduce via both create ramps
first: if the reporter's item came from the versions ramp, the empty state
firing would mean hasConfigurator is wrongly false despite an axis — a
different and deeper defect than the empty state's content; verify which
case this is before fixing.

## Related work
- DSGN-002 (the empty state came verbatim from its MainUnconfigured artboard) — prototype/php/work/3-done/
- DSGN-003 (the guided create that surfaces it first) — prototype/php/work/3-done/
- BUG-011 (Basics screen missing the panel — same component) — prototype/php/work/1-inbox/

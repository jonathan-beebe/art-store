---
id: BUG-013
type: bug
status: resolved
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

## Working

Confirmed the empty-state-content case before touching anything. Read
`ConfiguratorPageResolver::hasConfigurator()` (an axis, a variant, a modifier,
or a quantity break each earn it) and the guided-create versions ramp
(`ListingController::addVersionsAxis`), which unconditionally creates an
`OptionAxis` with option values — the existing DSGN-003 feature test
(`ListingControllerTest`: "creates a versions listing…") already pins that a
versions-ramp listing ends up with one `OptionAxis` row. `hasConfigurator` is
therefore correctly `true` for both create ramps that add an axis; only the
ramp-1 "one thing" listing (no axis, no variant, no modifier, no quantity
break) reaches the empty branch. No deeper defect — this is the shallow case:
the empty state's content, not its trigger.

Fixed `resources/views/components/seller/buyer-view.blade.php`: the
`! $hasConfigurator` branch now renders the listing's title, its price
(`$listing->price()->format()`), an honest stock line
(`App\Domain\Listings\ListingStockLabel::withInStock($listing->quantity)` —
the same DSGN-003 helper the seller edit hub already uses for "Made to
order" / "N in stock"), and a static, inert "Add to cart" pseudo-button —
same `aria-disabled` span pattern the configured branch already uses for its
own inert controls, so the button's presence and text never depend on stock
or publish status, matching how the configured branch already ignores
`canAddToCart` for that same span. Stock reads off quantity alone, not
`Listing::isPurchasable()`: a fresh guided-create listing saves as a draft,
and gating on publish status would show "Sold" for a healthy, just-created,
unpublished item — the opposite of the fix.

Tests (`App\View\Components\Seller\BuyerViewTest`):
- `BUG-013: shows the title, price, and an inert add-to-cart presentation for an unconfigured listing`
- `BUG-013: shows the made-to-order label for an unconfigured listing with no fixed quantity`
- `BUG-013: shows the zero-stock label for an unconfigured listing that has sold out`

Replaced the old `renders an unconfigured listing with a plain notice
instead of controls` test (asserted the removed sentence) with the first
test above. Updated `ListingControllerTest`'s hub-level empty-state test
(`BUG-013: shows the buyer-view panel as a title, price, and add-to-cart
preview for an unconfigured listing on the hub`) the same way. Configured
listings keep their current rendering — the file's existing configured-path
tests (priced options, breakdown total, no live form, greyed-out
unavailable option, B1/B2/B7/C3/B6, the caption suffix) all still pass
unchanged and pin that path.

Full suite: 2721 passed. Coverage: 100%.

### Refactor suggestions (not done)
- `ListingStockLabel::withInStock(0)` reads as "0 in stock" — plain and
  honest, but a seller skimming might parse it as a typo before a real
  count. A dedicated "Sold out" case (quantity === 0) would read more
  intentionally if this comes up again; left out here since the ticket
  asked to reuse the existing DSGN-003 helper, not add a new label.
- The panel now duplicates the "read quantity, ignore status, render a
  static inert control" pattern in two places (this new branch and the
  configured branch's `canAddToCart`-ignoring buttons). Not enough shared
  logic yet to justify extracting, but a third case would.

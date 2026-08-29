---
id: IMPRV-013
type: improvement
status: resolved
created: 2026-08-27
resolved: 2026-08-27
---

# IMPRV-013: Show the buyer-view preview panel on the main listing edit hub

## Problem
The seller listing edit hub (prototype/php/src/resources/views/seller/listings/edit.blade.php, e.g. /seller/listings/{listing}/edit) has no buyer-view preview panel of its own; the `<x-seller.buyer-view>` component only appears on the six configurator sub-screens (Choices, Combinations & stock, Questions, Pieces, Discounts, Page sections).

## Goal
A seller sees what a buyer sees from the main edit screen itself, not only after drilling into a sub-screen.

## Outcome
The listing edit hub renders the same buyer-view preview panel the sub-screens already show, kept in sync with the listing's current configuration.

## Why it matters
The buyer-view panel was confirmed valuable in DSGN-001's own human review; the hub is the page a seller lands on first and returns to most, and it is currently the one place in the redesigned editor without that reassurance.

## Discovery notes
Requested directly against a live listing (/seller/listings/lst_01M128EBWPGB42G0ARSYMB999A/edit). The component to reuse is `<x-seller.buyer-view>` (app/View/Components/Seller/BuyerView.php), already used identically on all six sub-screens — a starting point, not a mandated placement or layout.

## Related work
- prototype/php/work/3-done/DSGN-001-seller-problem-driven-configurator-ui.md (punch-list item 3, previously accepted as "omit — buyer panels [on sub-screens] cover it"; reopened here for the hub specifically)

## Resolution

2026-08-27 — Landed with DSGN-002 (commit d8eec2c): the row-based hub
renders `<x-seller.buyer-view>` in a fixed right column beside the
summary rows, in both hub states (configured, and the unconfigured
"nothing here yet for a buyer to configure" empty state), covered by
the hub feature tests in ListingControllerTest.

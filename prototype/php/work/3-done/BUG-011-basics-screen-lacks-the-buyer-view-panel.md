---
id: BUG-011
type: bug
status: resolved
created: 2026-08-27
---

# BUG-011: The Basics screen lacks the buyer-view panel

## Problem
The "Your item" detail screen
(prototype/php/src/resources/views/seller/listings/basics/edit.blade.php,
seller.listings.basics.edit) renders the form alone. Every other editing
surface in the redesigned configurator — the hub and all six section
screens — carries the `<x-seller.buyer-view>` panel beside its controls;
Basics is the one screen without it, and title/description/category are
exactly the fields whose buyer-visible consequence a seller edits most.

## Goal
Every editing surface shows the buyer's view, Basics included.

## Outcome
The Basics screen renders the same buyer-view panel its sibling screens
show, reflecting the listing's current state while the seller edits the
basics.

## Why it matters
The buyer-view panel was human-confirmed as a must-keep in DSGN-001's
review; its absence on the screen holding the listing's title and
description is the pattern breaking exactly where it started.

## Discovery notes
Reported live from the Basics screen. The DSGN-002 canvas's
BasicsDetail artboard drew the form without a panel (max-width 640) —
the mock's omission carried into implementation; the reporter overrules
it. The component and its page-data path are already shared by seven
screens; layout parallels the hub's 1fr/380px grid.

## Related work
- prototype/php/work/3-done/DSGN-002-retire-legacy-form-unify-editor-into-rows.md
- prototype/php/work/3-done/IMPRV-013-buyer-view-panel-on-the-listing-edit-hub.md

## Working
Changed `src/resources/views/seller/listings/basics/edit.blade.php`: wrapped
the existing form and attributes form in the hub's 1fr/380px grid and added
`<x-seller.buyer-view :listing="$listing" />` in the right column. No
controller or page-data change — `ListingBasicsPageData::for()` already
passes `listing` to the view.

Test added: `it shows the buyer-view panel beside the basics form` in
`src/app/Http/Controllers/Seller/ListingBasicsControllerTest.php`. Confirmed
it failed against the unmodified view (missing "What buyers see"), then
passed after the fix.

Full suite: 2718 passed (7755 assertions).

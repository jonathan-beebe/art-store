---
id: IMPRV-014
type: improvement
status: resolved
created: 2026-08-27
---

# IMPRV-014: Remove the physical-goods-only footer line from the listing edit hub

## Problem
The listing edit hub (prototype/php/src/resources/views/seller/listings/edit.blade.php:216) renders the fixed line "Art Store sells physical goods — digital downloads and file delivery aren't supported yet." on every listing's edit screen.

## Goal
The edit hub stops carrying that line.

## Outcome
The listing edit hub no longer shows the physical-goods-only sentence.

## Why it matters
Requested directly; the line is no longer wanted on the edit screen.

## Discovery notes
Stories C8, D7, and D8 in DSGN-001's coverage map currently point to this line as their "honest note" placement (prototype/php/work/3-done/DSGN-001-seller-problem-driven-configurator-ui.md, Appendix A). Removing it with no replacement re-opens those stories as having no visible answer anywhere in the seller UI — worth deciding whether to relocate the note or drop the honest-note commitment for those stories.

## Related work
- prototype/php/work/3-done/DSGN-001-seller-problem-driven-configurator-ui.md

## Working

Removed the fixed line "Art Store sells physical goods — digital downloads
and file delivery aren't supported yet." from
`src/resources/views/seller/listings/edit.blade.php` (was line 197, directly
under the "editing a live listing" note).

Test touched: `App\Http\Controllers\Seller\ListingControllerTest`, the test
named `it shows the physical-goods footer line` — renamed to `it does not
show the physical-goods footer line` and its assertion flipped from
`assertSee` to `assertDontSee` on the same string. Confirmed red against the
line still in place, then green after removal. This was the only test
matching the sentence (grepped "physical goods" / "digital downloads" across
`src/`); no other story-named tests reference it.

Per DSGN-001's coverage map (`work/3-done/DSGN-001-...md`, "C8, D6, D7, D8 |
honest notes | Seller/ListingControllerTest (footer/hint tests)"), this
footer line was the "honest note" placement for stories C8, D7, and D8. D6
has its own separate placement (the Medium custom-value hint test) and is
unaffected. With the line gone and no replacement, C8/D7/D8 now have no
honest-note answer anywhere in the seller UI — that gap is unresolved by
this ticket and left for the human to decide (relocate vs. drop the
honest-note commitment for those three stories).

Full suite: 2729 passed, 7785 assertions, 100% lines (`make check` green).

### Decision (2026-08-28, human)
The honest-note commitment for C8/D7/D8 is dropped — no relocation. The
0-refine decision file is discarded.

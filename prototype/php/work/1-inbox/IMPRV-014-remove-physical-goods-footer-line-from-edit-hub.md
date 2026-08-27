---
id: IMPRV-014
type: improvement
status: open
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

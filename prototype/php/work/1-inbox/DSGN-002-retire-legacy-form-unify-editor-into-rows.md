---
id: DSGN-002
type: design
status: open
created: 2026-08-27
---

# DSGN-002: Retire the legacy listing form; unify the editor as row summaries with buyer preview

## Problem
The listing create/edit hub (prototype/php/src/resources/views/seller/listings/form.blade.php, embedded in edit.blade.php) still opens with the pre-DSGN-001 flat form — raw title/description/dimensions/price/quantity fields — sitting above the DSGN-001 row summaries (Choices you offer, Combinations & stock, Questions you ask the buyer, Individual pieces, Quantity discounts, Listing page sections). A seller filling in the old form's single "price" and "how many" fields is effectively naming a first option/combination in vocabulary the new screens have already replaced elsewhere on the same page.

## Goal
A seller sees one consistent editing pattern for the whole listing, not a flat legacy form sitting above a row-based configurator.

## Outcome
The listing editor presents every part of the listing — basics (title, description, category, medium, and other core metadata), images, and each configurator section — as a summary row with its own edit affordance, consistent with how Choices/Combinations/Questions/Pieces/Discounts/Page sections already read; the buyer-view preview sits beside the rows; and the base-price-vs-price-difference question for a single-choice product (e.g. a print offered in several sizes, where each size might be thought of as having its own price rather than a base plus a delta) has a settled design answer.

## Why it matters
Sellers currently hold two competing mental models on one page — a flat form and a section-by-section configurator — and the flat form's "price"/"quantity" fields stop making sense the moment a listing needs choices, which is exactly when a seller most needs the new screens to feel authoritative.

## Discovery notes
Raised while reviewing the DSGN-001 delivery, prompted by walking through how a multi-size photograph listing gets built today. The reporter specifically wants a Basics row (title/description/category/medium/metadata) and an Images row (plural — today's form takes one image) added to the row pattern, both left of a fixed buyer-preview panel, each row's edit affordance opening a detail screen — extending DSGN-001's progressive-disclosure pattern up into the hub's own basics rather than leaving them as an embedded form. Also raised as an open question, not a directive: whether a size/variant "just has a price" (no privileged base) should be a real supported mode alongside today's base-price-plus-difference model, since sellers of some products (prints) may think in one, the other, or want both.

## Related work
- prototype/php/work/3-done/DSGN-001-seller-problem-driven-configurator-ui.md

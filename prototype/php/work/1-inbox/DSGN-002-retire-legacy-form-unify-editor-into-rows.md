---
id: DSGN-002
type: design
status: open
created: 2026-08-27
---

# DSGN-002: Retire the legacy listing form; unify the editor as row summaries with buyer preview

## Problem
The listing create/edit hub (prototype/php/src/resources/views/seller/listings/form.blade.php, embedded in edit.blade.php) still opens with the pre-DSGN-001 flat form — raw title/description/dimensions/price/quantity fields — sitting above the DSGN-001 row summaries (Choices you offer, Combinations & stock, Questions you ask the buyer, Individual pieces, Quantity discounts, Listing page sections). A seller filling in the old form's single "price" and "how many" fields is effectively naming a first option/combination in vocabulary the new screens have already replaced elsewhere on the same page.

Separately, two different pricing patterns show up on real products, and the option-axis model (app/Models/OptionValue's `surcharge_cents`) only represents one of them. Some products' variations are each priced standalone — a poster in small/medium/large, where every size just has a price and none is privileged as "the" price the others differ from. Other products' variations are genuine add-ons on top of a base price — an engraving upcharge, a nicer paper stock — which is what `surcharge_cents` already models. A seller with the first kind of product is forced through the second kind's mental model today.

## Goal
A seller sees one consistent editing pattern for the whole listing, not a flat legacy form sitting above a row-based configurator, and can price a choice's options the way their product actually works — standalone prices or add-on deltas — without forcing one shape onto the other.

## Outcome
The listing editor presents every part of the listing — basics (title, description, category, medium, and other core metadata), images, and each configurator section — as a summary row with its own edit affordance, consistent with how Choices/Combinations/Questions/Pieces/Discounts/Page sections already read; the buyer-view preview sits beside the rows. Separately, both real pricing patterns are supported: a choice whose options are each priced on their own (no option treated as a base the others differ from) and a choice whose options are priced as add-ons over a base price (today's surcharge model) — as either two distinct, clearly named mechanisms, or one mechanism that honestly represents both without a seller having to fake one pattern as the other.

## Why it matters
Sellers currently hold two competing mental models on one page — a flat form and a section-by-section configurator — and the flat form's "price"/"quantity" fields stop making sense the moment a listing needs choices, which is exactly when a seller most needs the new screens to feel authoritative. On pricing specifically, a seller whose product's variations are each independently priced has no honest way to express that today; they either fake a base price and back into deltas, or the price shown to buyers doesn't match how the seller actually thinks about their own product.

## Discovery notes
Raised while reviewing the DSGN-001 delivery, prompted by walking through how a multi-size photograph listing gets built today. The reporter specifically wants a Basics row (title/description/category/medium/metadata) and an Images row (plural — today's form takes one image) added to the row pattern, both left of a fixed buyer-preview panel, each row's edit affordance opening a detail screen — extending DSGN-001's progressive-disclosure pattern up into the hub's own basics rather than leaving them as an embedded form. The two pricing patterns in Problem/Outcome above are confirmed real by the reporter, not hypothetical — both need a legitimate place in the design, not a decision that one covers the other.

## Related work
- prototype/php/work/3-done/DSGN-001-seller-problem-driven-configurator-ui.md

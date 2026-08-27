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
The listing editor presents every part of the listing — basics (title, description, category, medium, and other core metadata), images, and each configurator section — as a summary row with its own edit affordance, consistent with how Choices/Combinations/Questions/Pieces/Discounts/Page sections already read; the buyer-view preview sits beside the rows. Separately, both real pricing patterns are supported: a choice whose options are each priced on their own (no option treated as a base the others differ from) and a choice whose options are priced as add-ons over a base price (today's surcharge model) — as either two distinct, clearly named mechanisms, or one mechanism that honestly represents both without a seller having to fake one pattern as the other. A seller setting up a choice can always tell, without guessing, which of the two pricing patterns they're configuring. And prototype/php/docs/item-configurator.md carries this as first-class design — its data model, seller flow, and price-resolution sections (currently §2, §3, §4) describe the shipped shape of both the row-based editor and whichever pricing mechanism(s) result, not left to the code and this ticket alone to explain.

## Why it matters
Sellers currently hold two competing mental models on one page — a flat form and a section-by-section configurator — and the flat form's "price"/"quantity" fields stop making sense the moment a listing needs choices, which is exactly when a seller most needs the new screens to feel authoritative. On pricing specifically, a seller whose product's variations are each independently priced has no honest way to express that today; they either fake a base price and back into deltas, or the price shown to buyers doesn't match how the seller actually thinks about their own product. This is a first-class change to the configurator's design, not a screen tweak — it changes the data model's pricing story (item-configurator.md §2–§3) and needs to read as clearly in the seller's UI as it does in that doc, or a seller can set up a choice without realizing which pricing rule will apply to it.

## Discovery notes
Raised while reviewing the DSGN-001 delivery, prompted by walking through how a multi-size photograph listing gets built today. The reporter specifically wants a Basics row (title/description/category/medium/metadata) and an Images row (plural — today's form takes one image) added to the row pattern, both left of a fixed buyer-preview panel, each row's edit affordance opening a detail screen — extending DSGN-001's progressive-disclosure pattern up into the hub's own basics rather than leaving them as an embedded form. The two pricing patterns in Problem/Outcome above are confirmed real by the reporter, not hypothetical — both need a legitimate place in the design, not a decision that one covers the other. Given the size of this change, the reporter expects it to go through another /design canvas round the way DSGN-001 did, human-reviewed before implementation, rather than being designed inline in code or in this ticket.

## Related work
- prototype/php/work/3-done/DSGN-001-seller-problem-driven-configurator-ui.md
- prototype/php/docs/item-configurator.md (§2 Data model, §3 Price and availability resolution, §4 Seller flow — to be updated by this ticket's design)

## Working

2026-08-27 — Started. Re-validated against current code: the legacy form
(prototype/php/src/resources/views/seller/listings/form.blade.php, embedded
in edit.blade.php) still opens the hub with raw title/description/dimensions/
price/quantity fields; OptionValue's only price field is `surcharge_cents`
(app/Models/OptionValue.php), which is always relative to the listing's base
`price_cents` — there is no standalone-price mode for a choice's options, so
the problem as stated still holds.

`.claude/skills/work-start/types/design.md` — the type-specific workflow this
ticket type would normally read next — is an unfilled stub ("TO BE DEFINED —
owner: human"). Proceeded on this ticket's own recorded expectation instead
(Discovery notes above, and DSGN-001's own precedent: a /design canvas,
published for human review, before any implementation).

2026-08-27 — Design canvas published for human review at
https://claude.ai/code/artifact/4f42ef84-0724-46f6-a2f3-98d1978bb159 (6
artboards, 2 pages). Implementation waits on that review, the same gate
DSGN-001 held to.

Covers: the row-based hub in both its configured state (a poster listing
with a Size choice and a Frame choice) and its unconfigured state (no
choices yet — price and stock stay on "Your item" until a first choice is
added, then move there); new detail screens for "Your item" (basics) and
"Images" (plural, replacing the single-image field); and the Choices screen
carrying the pricing-mode mechanism directly — a choice declares "each
option priced on its own" or "adds to your price" once, at creation
(mirroring how a Question already declares its kind at creation), each
option row's field labeled and priced accordingly. A second page shows that
mechanism as two directions — the creation-time choice (built out, leading
candidate) against a same-choice mode toggle (alternate, with its
unresolved value-conversion problem named as the reason it's not the
lead) — for the human to confirm or overrule.

Not yet touched: prototype/php/docs/item-configurator.md and the
Combinations & stock / Questions / Individual pieces / Quantity discounts /
Listing page sections rows' own detail screens, which this canvas leaves as
the existing DSGN-001 screens — the ticket's outcome only asked the row
shape and the pricing mechanism to be settled, not those screens redrawn.

2026-08-27 — Second pass against the real app (edit.blade.php,
option-axes/index.blade.php, buyer-view.blade.php) found and fixed: the page
background was plain white instead of the seller layout's gray-100 field;
the standalone-pricing tag used a blue accent the app's palette (gray plus
red/green only) never uses, replaced with a dark-fill vs light-border
distinction; the buyer-preview panel's controls looked live and clickable
instead of the real component's disabled/inert treatment, and was missing
its itemized Price/Total breakdown — rebuilt to match buyer-view.blade.php
exactly; the Images detail screen showed 3 images while the hub row claimed
5; an "Individual pieces" row was missing from both hub states; and two
screens used different wording for the same pricing-mode tag. Republished to
the same link.

---
id: DSGN-003
type: design
status: open
created: 2026-08-27
---

# DSGN-003: Guided new listing — three pricing on-ramps

## Problem
`GET /seller/listings/create` still renders the pre-DSGN-002 flat form
(create.blade.php including form.blade.php: title, description,
dimensions, price, quantity, category, image on one screen) — BUG-009
records that. Beyond retiring the form, the create moment asks nothing
about how the seller's product is actually priced, while the editor now
supports three real shapes: a simple item (one price, no choices), an
item in versions each carrying its own price (a choice priced
standalone), and an item with a base price plus extras (add-on choices).
Every seller gets the same undifferentiated form and must discover the
right tools afterwards; the mode-2 seller is even asked for a base price
the system will immediately overwrite with the synced standalone sum.

## Goal
Creating a listing routes the seller to the right pricing tools from
their own description of the product.

## Outcome
The create flow opens by asking, in the seller's words, which shape the
item is — one thing at one price / versions each with its own price /
one price plus extras — and each answer lands the seller in a flow that
collects exactly what that shape needs (the versions path asks for no
base price), ending on the row-based editor hub. The three answers are
on-ramps: any listing can still grow the other shapes afterwards
(the poster that starts as priced versions adds a framing upcharge
later), which the editor already supports. The legacy flat form has no
remaining consumer. The design goes through a /design canvas round,
human-reviewed before implementation, and the shipped flow lands in
docs/item-configurator.md §4.

## Why it matters
The first screen a seller meets is the one place the redesigned
configurator still speaks the old design, and the create moment is when
routing to the right tool is cheapest — the whole configurator exists to
let sellers set up their real product without translating it into the
platform's model.

## Discovery notes
The three shapes were named by the reporter and map onto shipped
mechanisms 1:1 (no choices / standalone choice / add-on choice) — this
is creation-time guidance over the existing model, and the maker should
keep it that way: modes route, they never constrain, since the 2+3
hybrid is already shipped and seeded (Sunset Ridge). JS-off holds:
radio-cards plus a submit, or per-card submits. Create collects the
minimum per shape; the hub's rows and publish checklist already guide
everything else (category, facts, images, sections).

## Related work
- BUG-009 (prototype/php/work/1-inbox/ — this design owns its fix; BUG-009 closes with DSGN-003's implementation)
- prototype/php/work/3-done/DSGN-002-retire-legacy-form-unify-editor-into-rows.md
- prototype/php/work/3-done/DSGN-001-seller-problem-driven-configurator-ui.md
- prototype/php/docs/item-configurator.md §4

## Working

2026-08-27 — Design canvas published for human review at
https://claude.ai/code/artifact/81f41519-c5ae-40bb-bd13-5efa378f8524
(5 artboards). Implementation waits on that review, the same gate
DSGN-001/002 held to.

Covers: the create screen asking title + the pricing-shape question as
three radio cards in the seller's words (with a "this just picks your
starting point" reassurance); one landing screen per ramp — one-thing
(price + how many, made-to-order blank-count hint), priced-versions
(choice name + version/price rows, no base price asked — the stored
price is DSGN-002's synced derivation), price-plus-extras (item price +
a skippable first add-on choice); and a flow map stating the composition
rule (ramps route, never constrain; the versions-plus-extras hybrid is
already shipped — Sunset Ridge). Create collects only what the shape
needs; images, category, facts, sections, questions, discounts, pieces
all stay with the hub's rows and publish checklist. BUG-009 (legacy
flat form) closes with this ticket's implementation.

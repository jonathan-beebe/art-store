---
id: FEAT-032
type: feature
status: resolved
created: 2026-08-27
---

# FEAT-032: Catalog-backed species axes

## Problem
docs/item-configurator.md §2.1 "Attribute altitude" (design pass, 2026-08-27) fixes the doctrine: browse-level properties are attributes; specific-type properties are category-granted and become attributes when there is no choice or catalog-backed axes when there is. The implementation is one step behind it: the walnut table's Wood axis (FEAT-031) is a custom label-only axis — "Walnut"/"Oak" are strings with no catalog linkage — and no specific-type property exists for wood at all, so the no-choice case (a fixed-species item) has nowhere structured to live. The schema already carries everything needed: `option_axes.property_id`, `option_values.property_value_id`, and per-category grants.

## Goal
Specific-type wood knowledge is catalog data end to end: a `Wood Species` property that a furniture seller states as an attribute when the species is fixed and offers as a catalog-backed axis when the buyer chooses.

## Outcome
- [x] `TaxonomySeeder` gains a `Wood Species` property (Walnut, Oak, Maple) granted on the furniture-hosting category with `usable_as_axis` and `usable_as_attribute`; the same category's `Medium` grant becomes `required`, closing the "species implies wood" curation rule from the doc.
- [x] The walnut table's Wood axis is re-seeded catalog-backed: `option_axes.property_id` references `Wood Species`, each option value references its catalog `property_value_id`; variants, overrides, and effective prices unchanged.
- [x] The garden gnome (fixed reclaimed oak, no choice) carries `Wood Species = Oak` as an attribute beside its Medium — the no-choice case demonstrated.
- [x] The seller axes screen lists the category's `usable_as_axis` grants before the custom-axis option, and choosing a catalog property pre-fills its option values from the property's values (each removable/editable before save) — the "Catalog axes first" screen note in doc §4.
- [x] Highlights renders specific-type attributes (`Wood Species: Oak` shows on the gnome; `Medium` stays suppressed per the FEAT-030 dedup decision).
- [x] Publish validation needs no new gate — `required` Medium on that category flows through the existing FEAT-029 gate; a test proves an uncategorized furniture listing still publishes and a categorized one without Medium refuses.
- [x] `make check` green; coverage 100%; journal updated.

## Why it matters
This lands the user-chosen model (option C in the 2026-08-27 design pass): the variant is structurally the walnut one, species choices become search-meaningful, and the fixed-species case stops living in prose — while staying one flat schema away from the hierarchy upgrade if it's ever needed.

## Discovery notes
- Read docs/item-configurator.md §2.1 "Attribute altitude" and §4 "Catalog axes first" — this ticket implements those two sections verbatim.
- The FEAT-029 fix already scopes the axes screen's property picker to `usable_as_axis` grants; this ticket adds the pre-fill behavior and the seed data that makes the picker meaningful for furniture.
- ConfiguratorArchetypeSeeder's table block (FEAT-031 shape: Wood axis crossed with Length×Width, 8 variants) is the re-seed target; keep combo keys/overrides intact so FEAT-028's smoke assertions stay true.
- The jewelry `Metal` property (Gold/Silver/Rose Gold) already follows this pattern — cite it, don't rebuild it.
- Sequenced after BUG-005/BUG-006 land (both touch the variants screen area).

## Related work
- FEAT-031 (vocabulary consolidation), FEAT-029 (grants + picker), docs/item-configurator.md §2.1/§4

## Working

**Grant shape** — `TaxonomySeeder` grants `Wood Species` (Walnut, Oak, Maple) on `Furniture` only, both flags true (`usable_as_attribute`, `usable_as_axis`), mirroring the existing Jewelry/Metal grant the ticket named as precedent. Furniture's `Medium` grant becomes `required: true` in the same seeder — the "species implies wood" curation rule, enforced with zero changes to `ConfiguratorPublishValidation` or `Listing::publishIssues()` (both already take `requiredAttributePropertyIds`/`attributedPropertyIds` generically).

**Walnut table** — `ConfiguratorArchetypeSeeder::walnutTable()` looks up the `Wood Species` property and passes it into `CreateOptionAxis` (already accepted an optional `?Property`), then links each `Walnut`/`Oak` option value to its catalog `PropertyValue` through `AddOptionValue`'s existing `?PropertyValue $propertyValue` parameter — both actions already carried this plumbing from FEAT-025/026, unused until now. Surcharges, price overrides, and the 8-variant sparse grid are untouched.

**Garden gnome** — `ListingSeeder` writes a second raw `listing_attributes` row (`Wood Species = Oak`), the same direct-write pattern already used for its second `Medium` value (Wood). The gnome's category (Home Goods) does not itself carry a `Wood Species` grant — the doctrine's curation rule binds a category that *grants* the specific-type property to also require the broad one, not every listing that states a specific-type fact; `ListingHighlights` and the seller attribute-editing screen (`ListingAttributeSection`, `SetListingAttributes`) read/write off `listing_attributes` rows and category grants independently, so nothing about the gnome's rendering depends on Home Goods holding the grant.

**Pre-fill, no JS** — `OptionAxisController::store()` now also takes `AddOptionValue`; when the submitted `property_id` resolves to a `Property`, it loops the property's own `PropertyValue`s (ordered by position) and adds one `OptionValue` per value (first marked default, `property_value_id` set), before redirecting back to the same index page — so the values land as ordinary rows using the edit/remove forms the page already had. The picker's `<select>` now lists catalog properties before the `Custom label` option in both the per-axis edit form and the "Add an axis" form (doc §4's "Catalog axes first"); the per-axis edit form also gained an explicit `@selected($axis->property_id === null)` on `Custom label` so a real custom axis still renders its own selection correctly now that it no longer sits first in DOM order.

**Verified live** (`make fresh` + tinker/curl against localhost:8000): the walnut table's `Wood` axis carries `property_id` = Wood Species and both option values carry their catalog `property_value_id`; the Furniture-category picker offers exactly `Wood Species`; a rendered axes-screen view shows `Wood Species` before `Custom label` in the picker markup; `/art/garden-gnome-in-reclaimed-oak` renders "Wood Species" / "Oak" under Highlights; `/art/live-edge-walnut-dining-table` still prices and lists every cell.

### Numbers
2335 tests, 6734 assertions, 100% lines (baseline was 2329/6714).

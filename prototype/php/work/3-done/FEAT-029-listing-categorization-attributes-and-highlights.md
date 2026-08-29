---
id: FEAT-029
type: feature
status: resolved
created: 2026-08-26
---

# FEAT-029: Listing categorization, attributes, and Highlights

## Problem
FEAT-025 landed the taxonomy layer (categories, properties, grants, listing_attributes) and FEAT-026 landed the configurator screens, but no seller screen sets `listings.category_id` or writes a `listing_attributes` row — the taxonomy is reachable only from seeds. Consequences found during FEAT-026 review: the axes screen's catalog-property picker is unreachable in practice, and the design's "required category attributes present" publish gate (docs/item-configurator.md §6) has no implementation in `ConfiguratorPublishValidation`.

## Goal
A seller assigns a category to a listing, fills in the category's attribute properties, is held to the required ones at publish, and the storefront renders the attributes as a Highlights panel.

## Outcome
- [x] The seller listing form (create + edit) gains a category select over the seeded tree (indented by depth or path-labelled; nullable — an uncategorized listing stays valid as today).
- [x] An attributes section on the listing edit flow renders one control per `category_properties` grant with `usable_as_attribute`: enum properties as a select over the property's values, honoring `multivalued` with a multi-select; writes go through an Action logging `listing.update`, behind the `listing_write` rate limit, ownership via the existing policy (wrong seller → 404).
- [x] Changing category prunes attribute rows whose property the new category does not grant (through the Action, covered by a test).
- [x] `ConfiguratorPublishValidation` gains the gate: every grant with `required` has at least one `listing_attributes` row; the issue links to the attributes section, same shape as the existing issues.
- [x] The axes screen's catalog-property picker offers the current category's `usable_as_axis` grants (verify it now renders for a categorized listing; fix if FEAT-026 left it keyed on anything else).
- [x] `/art/{slug}` renders a Highlights panel from `listing_attributes` (property name → value labels), matching the research's buyer-side Highlights pattern; absent attributes render nothing.
- [x] Archetype seeds updated so each configured archetype is categorized and carries believable attributes, exercising `required` and `multivalued` at least once.
- [x] Sidecar tests per new class; feature tests for the publish gate refusal and the Highlights render; `make check` green; coverage 100%.
- [x] `prototype/php/work/journal.md` updated: started/done lines.

## Why it matters
Category-gated properties are the design's first layer ("taxonomy gates everything"); without a screen that sets them, the search-facet and Highlights halves of the model stay dead code and the publish gate documented in docs/item-configurator.md §6 is a false claim about the system.

## Discovery notes
- Read docs/item-configurator.md §2.1 and §6, then app/Domain/Configurator/ConfiguratorPublishValidation.php and its test for the issue shape (including `subjectId` from FEAT-026).
- The seller form partial is resources/views/seller/listings/form.blade.php; the category select belongs there, the attributes section on the edit page beside the configurator links.
- TaxonomySeeder seeds the tree and grants; ConfiguratorArchetypeSeeder builds the archetypes through real Actions — extend both.
- Work this ticket only after FEAT-028: /art/{slug} is rebuilt by FEAT-027 and order flow by FEAT-028; the Highlights panel lands on the final page.

## Related work
- FEAT-025 (taxonomy schema and seeds), FEAT-026 (configurator screens; the unreachable catalog-property picker), FEAT-027 (/art/{slug} configurator), FEAT-028 (checkout)
- `__local__/item-configuration/etsy-product-configuration.md` §1.1, §2 (Highlights)
- prototype/php/docs/item-configurator.md §2.1, §4, §6

## Working

**Category on the main form, not a separate screen**: `ListingDraft` gained an optional seventh field, `categoryId`, threaded through `ListingRequest`/`CreateListing`/`UpdateListing` exactly like `title`/`price`/etc — the category select lives in `resources/views/seller/listings/form.blade.php`, shared by create and edit, ordered by `path` and indented by counting `/` segments in the path string (no new model method; path is already materialized). A category is nullable and behaves as full-replacement like every other drafted field: resubmitting the edit form without changing the select is not "no change", it is "set to the same value", the same as every other field on that form.

**Attributes section, a sync action**: `App\Actions\Configurator\SetListingAttributes` follows the shape `SetModifierScope` already set for "replace, don't add to" — one property at a time, deleting rows for values no longer checked and creating rows for values newly checked, capping a non-multivalued property to its first submitted value and silently dropping any property id or value id the listing's current category doesn't actually grant (defense in depth against a stale form after a category change, beyond what the UI ever offers). `App\Support\Configurator\ListingAttributeSection` is the one place that resolves "this listing's current attribute grants" and "this listing's existing selections", shared by the controller's happy path and its rate-limit re-render.

**Category-change pruning lives in `UpdateListing`**, the only action that ever changes `category_id`: it captures the category before writing the draft, and after writing, if the category changed, deletes any `listing_attributes` row whose property the new category does not grant as `usable_as_attribute` (an empty grant set — including "now uncategorized" — prunes everything). `SetListingAttributes` itself does no cross-property pruning; it only ever touches the grants it is handed.

**Publish gate scoped to `required && usable_as_attribute`**: the ticket's outcome line reads "every grant with `required` has at least one `listing_attributes` row", but two of the seeded `required` grants (Ring Size on Rings, Size on Apparel) are `usable_as_axis`-only — an axis property is buyer-selected per variant, not a fixed listing fact, and can never have a `listing_attributes` row by construction. Read literally, those listings could never publish. The gate implemented is `required && usable_as_attribute`, which is the only reading that is satisfiable and matches what `usable_as_attribute` and `usable_as_axis` are for in the schema. See Deviations.

**Axes picker fix**: `OptionAxisController::indexData()` offered every `Property` in the system regardless of the listing's category — the gap FEAT-026 review flagged. It now offers only the current category's `usable_as_axis` grants (none for an uncategorized listing), mirroring the new attributes-section scoping. `OptionAxisRequest` still accepts any valid property id at write time (unchanged) — the fix is what the picker offers, not a new server-side membership check on submit, consistent with how the modifier-scope picker already works.

**Highlights**: `App\Support\Configurator\ListingHighlights::forStorefront()` groups a listing's `listing_attributes` by property, in insertion order, into `[{name, values}]`; `/art/{slug}` renders it as a bright, no-`dark:` `<dl>` block that simply doesn't render when the list is empty. Verified live against the seeded archetypes (`make fresh` + curl): the pet portrait shows "Medium: Watercolor", the walnut table shows "Material: Walnut, Oak" (its multivalued grant), and the plain print — no attributes at all — shows no Highlights markup.

**Seeds**: `TaxonomySeeder`'s Furniture grant on Material is now `multivalued`; its Art grant on Medium is now `required`. `ConfiguratorArchetypeSeeder`'s walnut table carries both Walnut and Oak (exercising `multivalued`); the pet portrait now carries Medium: Watercolor (exercising `required`, and keeping `publishIssues()` empty for that archetype). Every configured archetype already carried a `category_id` before this ticket except the legacy zero-axis print, which stays uncategorized on purpose — it is the "no configurator data at all" archetype, and category assignment is independent of configurator data, so leaving it uncategorized also keeps one seed proving the nullable path.

**Decline/unit test gap**: added "restores a configured line's serialized unit to available on decline" to `DeclineFulfillmentTest` (the sibling of the existing variant-quantity case). It passed against the existing `StockMovement::release` path with no production change — `DeclineFulfillment` already locks and reads `items.unit` and calls the same shared restock branch `CancelOrder` does; only the variant case had a test.

### Deviations
- Publish-gate scope: implemented as `required && usable_as_attribute`, not every `required` grant — see Working. The literal outcome wording is unsatisfiable for an axis-only `required` grant (Ring Size, Apparel Size), which have no `listing_attributes` row to hold.

### Doc contradiction
`docs/item-configurator.md` §2.1 says "`required` gates publish validation (§5)" — publish validation is actually §6 (§5 is "Customer flow"). Cross-reference typo, not acted on beyond noting it here.

### Numbers
2291 tests, 6557 assertions, 100% lines. `make check` green (Pint, PHPStan level max/no baseline, full coverage gate). New: 1 Action (`SetListingAttributes`), 1 controller (`ListingAttributeController`), 1 `FormRequest` (`ListingAttributeRequest`), 3 support classes (`ListingAttributeSection`, `ListingEditPageData`, `ListingHighlights`), 1 new route (`PUT seller/listings/{listing}/attributes`).

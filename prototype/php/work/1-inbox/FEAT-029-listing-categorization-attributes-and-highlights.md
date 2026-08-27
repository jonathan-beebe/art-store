---
id: FEAT-029
type: feature
status: open
created: 2026-08-26
---

# FEAT-029: Listing categorization, attributes, and Highlights

## Problem
FEAT-025 landed the taxonomy layer (categories, properties, grants, listing_attributes) and FEAT-026 landed the configurator screens, but no seller screen sets `listings.category_id` or writes a `listing_attributes` row — the taxonomy is reachable only from seeds. Consequences found during FEAT-026 review: the axes screen's catalog-property picker is unreachable in practice, and the design's "required category attributes present" publish gate (docs/item-configurator.md §6) has no implementation in `ConfiguratorPublishValidation`.

## Goal
A seller assigns a category to a listing, fills in the category's attribute properties, is held to the required ones at publish, and the storefront renders the attributes as a Highlights panel.

## Outcome
- [ ] The seller listing form (create + edit) gains a category select over the seeded tree (indented by depth or path-labelled; nullable — an uncategorized listing stays valid as today).
- [ ] An attributes section on the listing edit flow renders one control per `category_properties` grant with `usable_as_attribute`: enum properties as a select over the property's values, honoring `multivalued` with a multi-select; writes go through an Action logging `listing.update`, behind the `listing_write` rate limit, ownership via the existing policy (wrong seller → 404).
- [ ] Changing category prunes attribute rows whose property the new category does not grant (through the Action, covered by a test).
- [ ] `ConfiguratorPublishValidation` gains the gate: every grant with `required` has at least one `listing_attributes` row; the issue links to the attributes section, same shape as the existing issues.
- [ ] The axes screen's catalog-property picker offers the current category's `usable_as_axis` grants (verify it now renders for a categorized listing; fix if FEAT-026 left it keyed on anything else).
- [ ] `/art/{slug}` renders a Highlights panel from `listing_attributes` (property name → value labels), matching the research's buyer-side Highlights pattern; absent attributes render nothing.
- [ ] Archetype seeds updated so each configured archetype is categorized and carries believable attributes, exercising `required` and `multivalued` at least once.
- [ ] Sidecar tests per new class; feature tests for the publish gate refusal and the Highlights render; `make check` green; coverage 100%.
- [ ] `prototype/php/work/journal.md` updated: started/done lines.

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

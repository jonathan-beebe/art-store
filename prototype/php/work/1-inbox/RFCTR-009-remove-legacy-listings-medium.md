---
id: RFCTR-009
type: refactor
status: open
created: 2026-08-27
---

# RFCTR-009: Remove the legacy listings.medium column

## Problem
`listings.medium` is a free-text column from before the taxonomy. FEAT-030 moved the storefront filter, search, and (with fallback) the listing display onto `listing_attributes`; FEAT-031 gives `Medium` one consolidated vocabulary. The column is now a shadow copy of the attribute — two places to state the same fact, one of them unvalidated prose.

## Goal
`listing_attributes` is the only place a listing's medium lives.

## Outcome
- [ ] The `medium` column is gone: removed from the `create_listings_table` migration (rewritten in place, `make fresh` rebuilds), the model's fillable list, the factory, and every seeder.
- [ ] The seller listing form drops its free-text Medium field; the attributes section is the one way to state a medium. `ListingDraft`/`ListingRequest`/`CreateListing`/`UpdateListing` lose the field.
- [ ] The listing page's Medium line reads the attribute only — the FEAT-030 legacy fallback is deleted; a listing with no Medium attribute shows no Medium line.
- [ ] No storefront or seller surface, log line, or test references `listings.medium`; grep proves it.
- [ ] docs updated where they mention the column (docs/item-configurator.md §9 note, docs/data-model.md if it lists listing columns, README seeded-data notes if they mention medium).
- [ ] `make check` green; coverage 100%; journal updated.

## Why it matters
A fact stored twice drifts twice. The configurator experiment's claim is "structure over prose" — keeping the prose column beside the structured attribute undercuts it on the platform's oldest field.

## Discovery notes
- Sequenced after FEAT-031 — removal assumes the consolidated vocabulary fully covers every seeded listing's display.
- FEAT-030's report lists the call sites it migrated; the fallback it added (`mediumAttributeLabel()` orelse column) is the main deletion target beyond the form.
- Node and Rails keep their `medium` column — this is a PHP-experiment divergence, same status as the configurator tables; note it in the ticket's Working section rather than touching docs/alignment.md.
- `CommerceTestCase::listing()` and factories may set `medium` — remove the attribute from fixtures and let tests that need a medium use the FEAT-030 `mediumAttribute()` helper.

## Related work
- FEAT-030 (attribute-backed filter), FEAT-031 (vocabulary consolidation — prerequisite)

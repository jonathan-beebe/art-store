---
id: RFCTR-009
type: refactor
status: resolved
created: 2026-08-27
---

# RFCTR-009: Remove the legacy listings.medium column

## Problem
`listings.medium` is a free-text column from before the taxonomy. FEAT-030 moved the storefront filter, search, and (with fallback) the listing display onto `listing_attributes`; FEAT-031 gives `Medium` one consolidated vocabulary. The column is now a shadow copy of the attribute — two places to state the same fact, one of them unvalidated prose.

## Goal
`listing_attributes` is the only place a listing's medium lives.

## Outcome
- [x] The `medium` column is gone: removed from the `create_listings_table` migration (rewritten in place, `make fresh` rebuilds), the model's fillable list, the factory, and every seeder.
- [x] The seller listing form drops its free-text Medium field; the attributes section is the one way to state a medium. `ListingDraft`/`ListingRequest`/`CreateListing`/`UpdateListing` lose the field.
- [x] The listing page's Medium line reads the attribute only — the FEAT-030 legacy fallback is deleted; a listing with no Medium attribute shows no Medium line.
- [x] No storefront or seller surface, log line, or test references `listings.medium`; grep proves it.
- [x] docs updated where they mention the column (docs/item-configurator.md §9 note, docs/data-model.md if it lists listing columns, README seeded-data notes if they mention medium).
- [x] `make check` green; coverage 100%; journal updated.

## Why it matters
A fact stored twice drifts twice. The configurator experiment's claim is "structure over prose" — keeping the prose column beside the structured attribute undercuts it on the platform's oldest field.

## Discovery notes
- Sequenced after FEAT-031 — removal assumes the consolidated vocabulary fully covers every seeded listing's display.
- FEAT-030's report lists the call sites it migrated; the fallback it added (`mediumAttributeLabel()` orelse column) is the main deletion target beyond the form.
- Node and Rails keep their `medium` column — this is a PHP-experiment divergence, same status as the configurator tables; note it in the ticket's Working section rather than touching docs/alignment.md.
- `CommerceTestCase::listing()` and factories may set `medium` — remove the attribute from fixtures and let tests that need a medium use the FEAT-030 `mediumAttribute()` helper.

## Related work
- FEAT-030 (attribute-backed filter), FEAT-031 (vocabulary consolidation — prerequisite)

## Working

**Column and model**: `create_listings_table` no longer has a `medium` column (rewritten in place); `Listing`'s `Fillable` attribute list drops it; `ListingFactory` no longer seeds a random legacy value. `ListingDraft` loses its `$medium` property and constructor/`of()` parameter entirely (dimensions moves up one position); `attributes()` no longer writes the key. `ListingRequest` drops the `medium` validation rule and the `toDraft()` argument. `CreateListing` and `UpdateListing` needed no code change — both write `$draft->attributes()` generically and never named the field directly.

**Seeders**: `ListingSeeder` and `WizardingSellerSeeder` keep their own internal `medium` array key (seed-authoring data used to pick a category and a Medium attribute label) but stop threading it into `ListingDraft::of()`. `ConfiguratorArchetypeSeeder`'s private `createListing()` wrapper drops its `$medium` parameter outright — none of its eight archetypes used that string for anything beyond the now-gone column.

**Seller form**: `resources/views/seller/listings/form.blade.php` drops the free-text Medium field; the attributes section (already data-driven off category grants, FEAT-029) is the only way to state one.

**Listing display, three surfaces**:
- Storefront (`shop/listing.blade.php`): the Medium `<dt>/<dd>` pair now renders only when `mediumAttributeLabel()` is non-null — no fallback, no "Mixed" default. A listing with no Medium attribute shows no Medium line at all.
- Admin (`admin/listings/show.blade.php`) — not named in this ticket's Outcome bullets but reads the same now-dropped column (`$listing->medium ?? '—'`) — updated to `$listing->mediumAttributeLabel() ?? '—'` so the admin listing page keeps showing a medium instead of silently going blank.
- Seller form: field removed (above), so there is nothing left to display there.

**Test fixtures**: every `CommerceTestCase::listing()` call and raw HTTP form post that set `'medium' => '...'` had the key removed; tests that need a Medium value now call the FEAT-030 `mediumAttribute()` helper instead, across `ListingRequestTest`, `Seller\ListingControllerTest`, `Shop\ListingControllerTest`, `Admin\ListingControllerTest`, `StorefrontControllerTest`, and `SmokeTest`. `ListingDraft::of()`'s dropped parameter updated every positional call in `CreateListingTest`, `UpdateListingTest`, and `ListingDraftTest`. `DatabaseSeederTest`'s column-based media-vocabulary assertion was removed (the sibling `listing_attributes`-based assertion already covers the same vocabulary); `WizardingSellerSeederTest`'s two column-reading assertions were rewritten against `listing_attributes` instead.

**Grep-proof**: `grep -rn "'medium'" --include=*.php` (excluding vendor) turns up only the seeders' own internal array key and `ListingSearch`'s unrelated `medium` URL-query-param field (FEAT-030's filter parameter name, never the column) — no reference to `listings.medium` remains anywhere in app code, views, or tests.

**Node and Rails**: both keep their `medium` column — this is a PHP-prototype-only divergence (the configurator tables are already PHP-only), not an alignment change; `docs/alignment.md` is untouched per this ticket's own discovery note.

**Docs**: `docs/item-configurator.md` §9's facets note ("read `listing_attributes`' Medium property first…") reworded to "exclusively" and cites RFCTR-009, since "first" implied a fallback that no longer exists. `docs/data-model.md`'s `listings` ER entity never listed `medium` (it was already a simplified column subset), so it needed no edit. No README in this prototype mentions the column.

### Numbers
2314 tests, 6677 assertions, 100% lines. `make check` green (Pint, PHPStan level max/no baseline, full coverage gate). Test count down by 1 from FEAT-031's baseline (2315 → 2314): `ListingRequestTest`'s "leaves an optional field the seller skipped null" dataset lost its `medium` case. No new classes, no new routes, no new log events. `listings` table: one column dropped.

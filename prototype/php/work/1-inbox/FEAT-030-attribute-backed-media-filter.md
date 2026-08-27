---
id: FEAT-030
type: feature
status: open
created: 2026-08-27
---

# FEAT-030: Attribute-backed media filter

## Problem
The storefront media filter and the configurator's attributes are two disconnected vocabularies. The filter dropdown, the `where`, and search's LIKE all read the legacy free-text `listings.medium` column (`Shop\StorefrontController`); the taxonomy's `Medium` property holds only the four values the archetype seeds needed (Print, Oil, Watercolor, Photograph) and `Material` only Walnut/Brass/Oak/Cotton. A shopper filters by "ceramic" from the legacy column while a seller cannot pick Ceramic in the attributes screen. `listing_attributes` drives no storefront surface (docs/item-configurator.md §9 defers facets), so the two truths never meet.

## Goal
One vocabulary: the taxonomy's `Medium` property covers everything the store sells, every seeded listing carries a `Medium` attribute, and the storefront filter, search, and listing-page display read attributes first.

## Outcome
- [ ] `TaxonomySeeder` extends the `Medium` property's values to cover the storefront vocabulary (Painting, Print, Ceramic, Textile, Sculpture, Photograph, Plant, Publication, Curio, Jewelry, Metal, Apparel, Walnut, Brass, Paper, Watercolor — dedupe/merge with the four existing values sensibly; labels title-cased) and grants `Medium` as `usable_as_attribute` on every category that hosts seeded listings.
- [ ] Every seeded listing (ListingSeeder, WizardingSellerSeeder, ConfiguratorArchetypeSeeder) is categorized and carries a `Medium` attribute matching its legacy `medium` string, written through `SetListingAttributes` (or the categorize path) — after `make fresh`, `listing_attributes` fully mirrors the store's media vocabulary.
- [ ] The storefront media filter reads attributes: the dropdown lists `Medium` property values having ≥1 active attributed listing (ordered by label), the filter `where` matches via `listing_attributes`, and the URL param stays `medium` with the value shape the page already uses. A listing without a `Medium` attribute appears under no media filter (same as a null `medium` today).
- [ ] Search matches the `Medium` attribute labels (replacing the LIKE on the legacy column); title/description matching unchanged.
- [ ] The listing page's "Medium" display line reads the `Medium` attribute when present, falling back to the legacy column for unattributed listings.
- [ ] The legacy `listings.medium` column stays (seller form untouched) — retiring it is an alignment decision across prototypes, out of scope here.
- [ ] docs/item-configurator.md §9's facets bullet updated: the media filter is now attribute-backed; the full facet UI remains deferred.
- [ ] `make check` green; coverage 100%; journal updated.

## Why it matters
This is the first storefront surface the taxonomy feeds — the "attributes feed search facets" half of the design stops being dead code, and the seller-facing attribute picker and the shopper-facing filter stop disagreeing about what a mug is made of.

## Discovery notes
- `app/Http/Controllers/Shop/StorefrontController.php` lines ~21–65: the `medium` submitted param, the LIKE search clause, the `where('medium', …)`, and the distinct-plucked dropdown are the four call sites to migrate.
- `app/Actions/Configurator/SetListingAttributes.php` and `app/Support/Configurator/ListingHighlights.php` (FEAT-029) are the write/read paths to reuse; filtering wants an Eloquent scope on `Listing` (e.g. `ofMediumAttribute(?string $valueId or label)`) with `null` adding no clause, matching the admin filter idiom.
- Decide the filter's value shape deliberately (property_value id vs label slug) and keep URLs readable; unrecognised value → the storefront's existing empty-result behavior, matching how an unknown legacy medium behaves today (verify, don't assume 400 — that's the admin idiom).
- Categorizing WizardingSellerSeeder's listings will touch its sidecar test pins; update them deliberately.
- Docker only, make targets from prototype/php; log events: seeder writes ride the existing vocabulary; no new events.

## Related work
- FEAT-029 (attributes + Highlights), FEAT-025 (taxonomy seed)
- docs/item-configurator.md §2.1, §9

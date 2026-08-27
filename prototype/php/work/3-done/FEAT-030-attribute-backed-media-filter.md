---
id: FEAT-030
type: feature
status: resolved
created: 2026-08-27
---

# FEAT-030: Attribute-backed media filter

## Problem
The storefront media filter and the configurator's attributes are two disconnected vocabularies. The filter dropdown, the `where`, and search's LIKE all read the legacy free-text `listings.medium` column (`Shop\StorefrontController`); the taxonomy's `Medium` property holds only the four values the archetype seeds needed (Print, Oil, Watercolor, Photograph) and `Material` only Walnut/Brass/Oak/Cotton. A shopper filters by "ceramic" from the legacy column while a seller cannot pick Ceramic in the attributes screen. `listing_attributes` drives no storefront surface (docs/item-configurator.md §9 defers facets), so the two truths never meet.

## Goal
One vocabulary: the taxonomy's `Medium` property covers everything the store sells, every seeded listing carries a `Medium` attribute, and the storefront filter, search, and listing-page display read attributes first.

## Outcome
- `TaxonomySeeder` extends the `Medium` property's values to cover the storefront vocabulary (Painting, Print, Ceramic, Textile, Sculpture, Photograph, Plant, Publication, Curio, Jewelry, Metal, Apparel, Walnut, Brass, Paper, Watercolor — dedupe/merge with the four existing values sensibly; labels title-cased) and grants `Medium` as `usable_as_attribute` on every category that hosts seeded listings.
- Every seeded listing (ListingSeeder, WizardingSellerSeeder, ConfiguratorArchetypeSeeder) is categorized and carries a `Medium` attribute matching its legacy `medium` string, written through `SetListingAttributes` (or the categorize path) — after `make fresh`, `listing_attributes` fully mirrors the store's media vocabulary.
- The storefront media filter reads attributes: the dropdown lists `Medium` property values having ≥1 active attributed listing (ordered by label), the filter `where` matches via `listing_attributes`, and the URL param stays `medium` with the value shape the page already uses. A listing without a `Medium` attribute appears under no media filter (same as a null `medium` today).
- Search matches the `Medium` attribute labels (replacing the LIKE on the legacy column); title/description matching unchanged.
- The listing page's "Medium" display line reads the `Medium` attribute when present, falling back to the legacy column for unattributed listings.
- The legacy `listings.medium` column stays (seller form untouched) — retiring it is an alignment decision across prototypes, out of scope here.
- docs/item-configurator.md §9's facets bullet updated: the media filter is now attribute-backed; the full facet UI remains deferred.
- `make check` green; coverage 100%; journal updated.

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

## Working

**Medium's expanded vocabulary**: `TaxonomySeeder`'s `Medium` property gained 13 values (Painting, Ceramic, Textile, Sculpture, Plant, Publication, Curio, Jewelry, Metal, Apparel, Walnut, Brass, Paper) alongside the four it already held (Print, Oil, Watercolor, Photograph kept as-is — Oil stays even though no seed uses it, on "dedupe sensibly" rather than "prune to exactly what's used"). Every legacy `medium` string across the three seeders title-cases straight onto one of these labels except `photography`, which maps to the already-existing `Photograph`. `Medium` is now granted `usable_as_attribute` on all seven taxonomy categories (Art keeps its existing `required: true`; the other six grants are optional), since every category ends up hosting at least one seeded listing.

**Categorization**: `ConfiguratorArchetypeSeeder`'s plain print (previously the one deliberately-uncategorized archetype) now sits under Art. `ListingSeeder` maps its six legacy media to categories (painting/print/photography → Art; ceramic/textile/sculpture → Home Goods) via a static lookup table rather than touching all 24 `entry()` call sites. `WizardingSellerSeeder` categorizes plant/curio/publication under Home Goods and both jewelry pieces under Jewelry. Every one of the 45 seeded listings now carries both a `category_id` and a Medium `listing_attributes` row — `ConfiguratorArchetypeSeeder` gained a shared `attribute(Listing, string $propertyName, string $label)` helper, replacing three separate inline `ListingAttribute::create` blocks (walnut table's Material Walnut/Oak, candlesticks' Material Brass, pet portrait's Medium Watercolor) and backing five new Medium attribute calls.

**Filter value shape**: the URL keeps `medium=<lowercase label>` (e.g. `medium=ceramic`), matching what the page already used — every Medium label in play is one word, so a plain lowercase is a fully readable slug with no hyphenation logic needed. `Listing::ofMediumAttribute(?string $medium)` (new scope, admin nullable-argument idiom) resolves the submitted value against `PropertyValue` labels case-insensitively in PHP (`mb_strtolower` compare, not a SQL `lower()`), then constrains via `listing_attributes`; a slug matching nothing yields zero matching value ids and so zero rows — the same emptiness an unrecognised legacy medium produced, verified live (`/?medium=bronze` with only "Oil" seeded returns nothing) rather than assumed.

**Search and dropdown**: `StorefrontController::matching()` replaces the LIKE on `medium` with an `orWhereHas('listingAttributes', …)` chain matching the Medium property's value labels; title/description LIKE clauses are untouched. `mediumOptions()` (renamed from `mediaForSale()`) returns `list<array{value, label}>` — every Medium value at least one for-sale listing carries, ordered by label — computed by first collecting for-sale listing ids (`Listing::query()->forSale()->pluck('id')`) and then querying `ListingAttribute`/`PropertyValue` against that id set, rather than a `whereHas` closure calling the `forSale()` scope directly (Larastan can't resolve a custom Eloquent scope method called inside a relation-constraint closure's generically-typed `Builder` parameter — this sidesteps that rather than fighting it).

**Listing-page Medium line**: `Listing::mediumAttributeLabel()` (new method) reads the listing's Medium attribute if one exists; `shop/listing.blade.php`'s Medium `<dd>` tries it first, then the legacy `medium` column, then "Mixed" — unchanged three-way fallback chain, just with the attribute inserted at the front.

**Highlights deduplication (addendum beyond the ticket's Outcome bullets)**: with Medium now attributed on nearly every listing, `ListingHighlights::forStorefront()` would otherwise echo the exact same "Medium: Ceramic" fact the page's own Medium line already shows, immediately below it. `ListingHighlights` now skips the Medium property explicitly — verified live that the walnut table (which carries both Material and Medium attributes) shows "Medium: Walnut" once at the top and "Material: Walnut, Oak" once in Highlights, no repeat.

**Verified live** (`make fresh` + curl against localhost:8000): the dropdown lists all sixteen in-use values (Oil excluded, nothing seeded carries it) alphabetically; `/?medium=ceramic` returns the mug plus the four for-sale ListingSeeder ceramic pieces (Burrow Kitchen Tea Bowl, Butterbeer Pitcher, Divination Tower Vase, Great Hall Serving Bowl), excluding the sold and draft ceramic pieces; `/?q=ceramic` returns the same set via the attribute-label search; the mug and walnut-table listing pages show the expected Medium lines.

### Deviations
- Excluded Medium from `ListingHighlights` (see Working) — not a stated Outcome bullet, but a direct, otherwise-visible consequence of this ticket's own change; flagging it here rather than leaving an unexplained extra edit.

### Numbers
2315 tests, 6649 assertions, 100% lines. `make check` green (Pint, PHPStan level max/no baseline, full coverage gate). New: 1 model scope + 1 method (`Listing::ofMediumAttribute`, `Listing::mediumAttributeLabel`), 2 shared test helpers (`CommerceTestCase::attribute`, `::mediumAttribute`). No new classes, no new routes, no new log events.

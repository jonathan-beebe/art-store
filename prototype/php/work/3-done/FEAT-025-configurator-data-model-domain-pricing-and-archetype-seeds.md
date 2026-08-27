---
id: FEAT-025
type: feature
status: resolved
created: 2026-08-26
---

# FEAT-025: Configurator data model, domain pricing, and archetype seeds

## Problem
`__local__/item-configuration/etsy-product-configuration.md` §2.1–2.2 catalogs six hacks sellers use because Etsy's model has no primitive for them: compound option strings (ring, wedding, pet portrait), config smuggled into personalization (fonts, paper stock), a product-type choice wearing an axis costume ("BLANK / EMPTY MUG"), quantity tiers as fake variation options (wedding), a 52-option axis standing in for serialized one-of-a-kind stock (candlesticks), and a hand-priced dimension matrix (walnut table). `listings` today (`price_cents`, `quantity`, nothing else) has no primitive for any of it. This ticket lands the schema, domain pricing, and seed data those primitives need; no UI.

## Goal
A listing can carry option axes, sparse variants, serialized units, scoped modifiers, quantity breaks, and description sections, with pure domain code that resolves price and availability for any configuration — with zero rows added, a listing behaves exactly as it does today.

## Outcome
- [x] Migrations for `categories` (`cat`), `properties` (`prp`), `property_values` (`pvl`), `category_properties` (`cpr`), `listing_attributes` (`lat`), `option_axes` (`axs`), `option_values` (`ovl`), `variants` (`vrt`), `variant_options` (`vop`), `units` (`unt`), `modifiers` (`mdf`), `modifier_options` (`mdo`), `modifier_scopes` (`mds`), `quantity_breaks` (`qbk`), `description_sections` (`dsc`) — all fields per the design doc §2 and §3, `$table->string('id', 30)->primary()` + `foreignUlid(..., 30)` per `docs/alignment.md` §1. `listings` gains nullable `category_id`. `cart_items`/`order_items` columns are **not** in this ticket — they land in FEAT-027/FEAT-028.
- [x] Eloquent models for every new table under `app/Models`, each using `HasPrefixedUlid` with its own `idPrefix()`, relations declared both directions (`Listing::optionAxes()`, `OptionAxis::listing()`, etc.), a factory under `database/factories` per model.
- [x] Pure domain code under `app/Domain/Configurator/` (or similarly named concept folder): combo-key computation (sorted option-value ids), variant price resolution (`price_override_cents ?? base + Σ surcharges`), availability resolution (`enabled ∧ (serialized → any available unit; else quantity NULL or > 0)`), modifier answer pricing (add-on + measurement × rate), quantity-break discount application, itemized breakdown assembly (label + cents per line), cart-line fingerprint (deterministic hash of variant + unit + answers; a listing with no axes gets the same constant fingerprint every time so the legacy cart unique index keeps working). `final readonly`, named factories, no `Illuminate`/clock/random — `tests/Arch.php` enforces this on `App\Domain`.
- [x] Seller-facing Actions under `app/Actions/Configurator/` (or per-concept) for creating/updating: option axes + values, variants (including bulk-generate-from-axes and per-cell override), units, modifiers + options + scopes, quantity breaks, description sections. Each wrapped in `Story::for(StoryEvent::ListingUpdate)->tell(...)` (or `ListingCreate` where a listing is being built). No controllers/views yet — these are called directly from tests and seeders.
- [x] Publish validation as pure domain code returning a list of issues (not yet wired to a controller): every enabled variant priced ≥ 0, one value per axis per variant, serialized variants have ≥1 available unit, caps respected (≤70 options/axis, ≤500 variants, ≤5 modifiers, ≤10 quantity tiers, ≤15 sections; axes uncapped). A failing check throws `DomainRuleViolation` carrying the full issue list (see `CarriesRefusalData`).
- [x] Taxonomy seed: a small believable category tree (jewelry, home goods, apparel, art, stationery — enough to host the 8 archetypes) with properties and `category_properties` grants exercising `usable_as_attribute`/`usable_as_axis`/`required`.
- [x] Demo seeds covering all 8 archetypes from the research, built through the real Actions above (never raw `Model::create` for configurator rows), added to `database/seeders` with a sidecar test: plain 8x10 print (zero axes — legacy path), engraved ring (2 axes + surcharge + font select modifier + text modifier, modifier scoped away from the option value where it doesn't apply), personalized mug (product-type axis + modifier scoped only to the personalized option values), POD tee (color × size, size-tier surcharges), walnut table (two dimension axes, sparse matrix, price overrides on cells), vintage candlesticks (one serialized variant, ~12 units with per-unit `specs_json`/condition), wedding invitations (size axis + priced paper-stock select modifier + quantity breaks), pet portrait (pets×pose axis + size/framing axis).
- [x] Sidecar test per new class (`tests/SidecarsTest.php` enforces); `make check` green; coverage 100% lines.
- [x] `prototype/php/work/journal.md` updated: FEAT-025 defined/started/done lines.

## Why it matters
Every later ticket (seller UI, buyer UI, checkout) sits on this schema and this pricing math. Getting the sparse-variant, scoped-modifier, and serialized-unit shapes right here — and proving them against all 8 archetypes as seeds, not just unit tests of the happy path — means FEAT-026 through FEAT-028 build UI over a model that already carries the design's hard cases, rather than discovering a gap in the schema midway through a UI ticket.

## Discovery notes
- Read `prototype/php/docs/architecture.md` in full before starting — layer rules (`app/Domain` pure, arch-enforced), the `Story::for(...)->tell(...)` pattern, `HasPrefixedUlid`/`IdMint::of('<prefix>')`, the clock convention (`DateTimeImmutable $now` as an action's last parameter, never `now()` inside `app/Domain` or an action).
- `app/Domain/Listings/ListingStatus.php` is the existing enum: `Draft → ForSale → Sold`, `Archived` from `Draft`/`ForSale`, `Sold → ForSale`. The design doc's "draft→published transition" is this stack's `Draft → ForSale`; do not invent a new status.
- `app/Actions/Listings/CreateListing.php` is the shape every new Action mirrors: constructor-injected collaborators, `Story::for(StoryEvent::...)->tell($msg, $data, function (Story $story) { ... $story->did(...); return $thing; })`.
- Everything runs in Docker via `make` targets from `prototype/php` — never host `php`/`composer`. `make fresh` rebuilds the DB; migrations may be rewritten in place, no data migrations needed.
- Tests are sidecars: `app/Foo.php` needs `app/FooTest.php` next to it. Pest `it()`/`test()` style. Fixture helpers go on `tests/CommerceTestCase` (`seller()`, `listing()`, `cartFor()`, `orderFor()`, `moment(...)`) — add configurator-specific fixtures there rather than a new base class.
- `combo_key`/fingerprint risk: get the sorted-ids serialization format nailed down here since FEAT-027's cart unique index and FEAT-028's order snapshot both depend on it matching exactly what the seller-side variant lookup produces.
- No SQL views (unlike the SQLite design doc's `v_variant_price`/`v_variant_available`) — this stack does resolution in Eloquent scopes + the pure domain functions above, per the architecture doc's core/adapter split.
- Log events: seller writes in this ticket ride `listing.update`/`listing.create` — the alignment §2.3 vocabulary is closed, do not mint new event names for axes/variants/units/modifiers.

## Working

Landed the full configurator schema, domain, actions, and seed data:

- **Migrations (16)**: `categories`, `properties`, `property_values`, `category_properties`, `listing_attributes`, `option_axes`, `option_values`, `variants`, `variant_options`, `units`, `modifiers`, `modifier_options`, `modifier_scopes`, `quantity_breaks`, `description_sections`, plus a nullable `listings.category_id`. No `delivery`/digital columns anywhere, per the hard environment rule. `is_default`/removal-style uniqueness is app-layer, not a partial index (SQLite has none — same convention `listing_removals` already uses).
- **Models + factories (15)**: one Eloquent model and factory per table, `HasPrefixedUlid` + `idPrefix()` each, relations declared both directions. `Listing` gained `category()`, `listingAttributes()`, `optionAxes()`, `variants()`, `modifiers()`, `quantityBreaks()`, `descriptionSections()`. `Variant::resolvedPrice()`/`availability()`/`axisIdsCovered()` and `Modifier::appliesTo()` bridge rows into the domain functions.
- **Domain (`app/Domain/Configurator/`, 21 classes)**: `ComboKey`, `VariantPrice`, `VariantAvailability`, `ModifierAnswerPrice`, `QuantityDiscount`, `PriceBreakdownLine`/`PriceBreakdown`, `CartLineFingerprint`, `VariantSnapshot`/`PublishIssue`/`ConfiguratorPublishValidation`/`ConfiguratorPublishRefused`, and four enums (`PropertyDataType`, `ModifierKind`, `UnitState`, `DescriptionSectionKind`). All `final readonly`, no Illuminate/clock/random; `tests/Arch.php` passes unchanged.
- **Actions (`app/Actions/Configurator/`, 11)**: `CreateOptionAxis`, `AddOptionValue`, `CreateVariant`, `GenerateVariants` (bulk cross-product, idempotent, skips entirely on zero axes so the legacy path gains no row), `UpdateVariant`, `AddUnit`, `CreateModifier`, `AddModifierOption`, `ScopeModifier`, `AddQuantityBreak`, `AddDescriptionSection` — all `Story::for(StoryEvent::ListingUpdate)`.
- **Seeds**: `TaxonomySeeder` (5 top-level categories, 2 nested, 7 properties, grants exercising all three flags) and `ConfiguratorArchetypeSeeder` (all 8 archetypes, one demo seller, built only through the actions above). Both wired into `DatabaseSeeder`.
- **Tests**: a sidecar per new class, plus seeder tests asserting the specific hard cases: the ring's font/text modifiers scoped away from "No Engraving", the mug's text modifier scoped only to "Personalized", the tee's size-tier surcharges, the walnut table's 4 sparse (of 6 possible) variants each carrying a `price_override_cents`, the candlestick's 12-unit derived availability, the invitations' per-option paper pricing and 3 quantity tiers, the pet portrait's 2-axis grid, and the plain print's zero configurator rows.
- Updated `DatabaseSeederTest` baselines for the new seller/listing counts and seeder count.

### Deviations
- Modifier kinds scoped to `Text`/`Select`/`Measurement` only (dropped the design doc's `file_upload`/`date`) — nothing in this ticket's 8 archetypes or domain bullets exercises them, and unused kinds would sit uncovered against the 100% gate.
- No fixture added to `CommerceTestCase` for configurator setup — every test's axis/variant/modifier setup is 2-4 lines with no repetition across files, so per the testing conventions ("a fixture used by one file is a closure... a repeated fixture is a `CommerceTestCase` method") none earned a shared method.
- `listing_attributes` and taxonomy rows are written with plain `Model::create` (no dedicated Action) — the ticket's Actions bullet lists only axes/variants/units/modifiers/quantity-breaks/sections; taxonomy and attribute grants are reference data, seeded the way `AdminSeeder`/`SellerSeeder` already write theirs.

### Numbers
Before: 1836 tests, 5010 assertions, 100% lines.
After: 1948 tests, 5628 assertions, 100% lines.

## Related work
- FEAT-026 (seller configurator UI, built on this schema)
- FEAT-027 (buyer configurator + cart)
- FEAT-028 (checkout + order snapshot)
- `__local__/item-configuration/etsy-product-configuration.md`
- `__local__/item-configuration/etsy-product-configuration-design-doc.md`
- `docs/alignment.md` §1 (ids), §2 (logging)

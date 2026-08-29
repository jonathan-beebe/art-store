---
id: FEAT-031
type: feature
status: resolved
created: 2026-08-27
---

# FEAT-031: One Medium vocabulary — high-level attributes, specific axes

## Problem
The attributes section offers two properties that make no sense side by side: `Material` with four values (Walnut, Brass, Oak, Cotton) and `Medium` with seventeen (FEAT-030's store vocabulary), overlapping (`Walnut`, `Brass` appear in both) and split by no principle a seller could guess. The altitude is wrong: an attribute is a fixed fact for browsing ("this is wood"), while the specific type is either a buyer choice (a wood-species option axis) or a descriptive spec.

## Goal
One attribute property, `Medium`, with high-level values; specific types live on option axes where the buyer chooses (the walnut table's wood species) and nowhere pretend to be a second attribute vocabulary.

## Outcome
- [x] The `Material` property, its values, its grants, and every `listing_attributes` row referencing it are gone from the taxonomy seed; no seller screen offers it.
- [x] `Medium`'s values consolidate to high-level: Painting, Print, Photograph, Ceramic, Textile, Sculpture, Wood, Metal, Paper, Plant, Publication, Curio, Jewelry, Apparel. Specific values (Oil, Watercolor, Walnut, Brass) are removed; seeded listings remap (watercolor → Painting, walnut → Wood, brass → Metal; the rest keep their obvious value).
- [x] `Medium` grants are `multivalued` where mixed media is plausible, and at least one seeded listing exercises a multivalued Medium (e.g. a curio as Wood + Metal) so the FEAT-029 multivalued behavior stays demonstrated after Material's removal.
- [x] The walnut table gains a **Wood** option axis (Walnut default, Oak) — the user-described pattern: attribute says Wood, the axis says which wood. Variants regenerate across Length × Width × Wood with the existing per-cell override prices carried to both wood options (same price per size unless a surcharge is more believable); publish validation stays satisfied.
- [x] The engraved ring keeps its Metal axis and gets Medium = Metal — the same pattern from the jewelry side.
- [x] Storefront filter, search, Highlights, and the listing Medium line all reflect the consolidated vocabulary after `make fresh` (`/?medium=wood` returns the table and the gnome; `ceramic` still returns the pottery).
- [x] docs/item-configurator.md updated where it names the Material property or the old vocabulary (taxonomy examples, seeds notes).
- [x] `make check` green; coverage 100%; journal updated.

## Why it matters
The attribute layer's promise is one browsable vocabulary. Two overlapping properties at different altitudes reintroduce the ambiguity the taxonomy exists to remove, one screen after it shipped.

## Discovery notes
- TaxonomySeeder holds both properties and the grants; ConfiguratorArchetypeSeeder attributes the archetypes (table's multivalued Material demo is the one being replaced); WizardingSellerSeeder/ListingSeeder carry the FEAT-030 medium mapping tables.
- The table's variant regeneration: GenerateVariants exists for the cross-product; overrides then set per cell (see the seeder's current 4-row override block). The publish gate demands one value per axis per variant — stale 2-axis combo_keys must not survive.
- The `Metal` catalog property (Gold/Silver/Rose Gold) is an axis property and stays — axes referencing catalog properties are unaffected by the attribute consolidation.
- FEAT-029 pinned Material in tests (seeder tests, attribute-section tests, Highlights tests) — update pins deliberately.
- Work this before RFCTR-009 (legacy column removal) — that ticket assumes attributes fully cover display.

## Related work
- FEAT-029 (attributes), FEAT-030 (media filter), RFCTR-009 (legacy medium removal — sequenced after this)

## Working

**Material's removal**: `TaxonomySeeder` no longer creates a `Material` property, its four values, or its two grants (Home Goods, Furniture). Every seller screen that listed it (the attributes section on the listing edit page) is data-driven off `category_properties.usable_as_attribute`, so it stopped offering Material with no template change.

**Medium's consolidation**: the property's fourteen values are `Painting, Print, Photograph, Ceramic, Textile, Sculpture, Wood, Metal, Paper, Plant, Publication, Curio, Jewelry, Apparel` — the ticket's list verbatim. `ConfiguratorArchetypeSeeder` remaps its three archetypes that carried a retired value: the pet portrait's Medium moves from Watercolor to Painting, the vintage candlesticks' from Brass to Metal, and the walnut table's from Walnut to Wood (see below). The engraved ring already carried Medium = Metal alongside its Metal axis (FEAT-030) — untouched, since it already matched this ticket's own worked example.

**Multivalued demo — deviation from the ticket's example**: the ticket's Outcome bullet suggests "a curio as Wood + Metal", but Luna Lovegood's seeded curio (Spectrespecs) is described as paper-and-plastic novelty glasses — Wood + Metal would contradict its own description. `TaxonomySeeder` instead makes Home Goods' Medium grant multivalued (Furniture's reverts to single-valued, since the walnut table's wood choice is now the Wood axis's job, not a second attribute value), and `ListingSeeder`'s "Garden Gnome in Reclaimed Oak" — already textually "carved from a single piece of reclaimed oak beam" — gets a second Medium value, Wood, alongside its automatic Sculpture value. Verified live: `/?medium=sculpture` and `/?medium=wood` both return the gnome.

**The walnut table's Wood axis**: a third axis, Wood (Walnut default, Oak), joins Length and Width. The four hand-priced Length × Width cells each spawn two variants — one per Wood value, same `price_override_cents` on both (the wood choice reads as stylistic, not a size surcharge, so no invented price difference) — eight variants in place of the old four, each covering all three axes (`GenerateVariants`' blind cross-product isn't used here, since the old 2-axis combo keys had to be replaced outright rather than added to; the seeder calls `CreateVariant` explicitly per cell × wood, as it already did for the sparse grid). The listing's Medium attribute is now the single value Wood, replacing the old two-row multivalued Material (Walnut, Oak) pair.

**Storefront verification** (`make fresh` + curl against localhost:8000): `/?medium=wood` returns the table and the gnome; `/?medium=ceramic` is unaffected (Burrow Kitchen Tea Bowl, Butterbeer Pitcher, Divination Tower Vase, Great Hall Serving Bowl, Stoneware Coffee Mug); the media dropdown lists all fourteen values, alphabetically, with no stale Oil/Watercolor/Walnut/Brass entries; the table's listing page Medium line reads "Wood", the gnome's reads "Sculpture" (its first-created attribute row — `mediumAttributeLabel()` takes the first Medium row, unchanged from FEAT-030; the gnome's second value, Wood, has no separate display line, matching how `ListingHighlights` already excludes the Medium property entirely, so a multivalued Medium's extra values were never surfaced there either).

**Docs**: `docs/item-configurator.md` names neither `Material` nor an itemized Medium vocabulary anywhere (confirmed by grep) — its one Medium-related line (§9, the facets note) describes attribute-backed filtering generically and needed no edit. No doc change was required beyond the code comments touched in `ListingHighlights` and `ListingAttribute` (their doc-comment examples cited `Material: Sterling Silver`, now `Metal: Gold`).

### Deviations
- Multivalued demo listing is the Home Goods sculpture (Sculpture + Wood), not a curio (Wood + Metal) — see above; the ticket's phrasing was an example ("e.g."), not literal.
- `docs/item-configurator.md` needed no edit — its Outcome bullet assumed the doc named Material or an itemized vocabulary; grep confirmed it does not.

### Numbers
2315 tests, 6690 assertions, 100% lines. `make check` green (Pint, PHPStan level max/no baseline, full coverage gate). No new classes, no new routes, no new log events. Property count: 7 → 6 (Material removed). Medium values: 17 → 14.

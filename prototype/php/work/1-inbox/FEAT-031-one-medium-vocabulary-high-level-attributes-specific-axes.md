---
id: FEAT-031
type: feature
status: open
created: 2026-08-27
---

# FEAT-031: One Medium vocabulary — high-level attributes, specific axes

## Problem
The attributes section offers two properties that make no sense side by side: `Material` with four values (Walnut, Brass, Oak, Cotton) and `Medium` with seventeen (FEAT-030's store vocabulary), overlapping (`Walnut`, `Brass` appear in both) and split by no principle a seller could guess. The altitude is wrong: an attribute is a fixed fact for browsing ("this is wood"), while the specific type is either a buyer choice (a wood-species option axis) or a descriptive spec.

## Goal
One attribute property, `Medium`, with high-level values; specific types live on option axes where the buyer chooses (the walnut table's wood species) and nowhere pretend to be a second attribute vocabulary.

## Outcome
- [ ] The `Material` property, its values, its grants, and every `listing_attributes` row referencing it are gone from the taxonomy seed; no seller screen offers it.
- [ ] `Medium`'s values consolidate to high-level: Painting, Print, Photograph, Ceramic, Textile, Sculpture, Wood, Metal, Paper, Plant, Publication, Curio, Jewelry, Apparel. Specific values (Oil, Watercolor, Walnut, Brass) are removed; seeded listings remap (watercolor → Painting, walnut → Wood, brass → Metal; the rest keep their obvious value).
- [ ] `Medium` grants are `multivalued` where mixed media is plausible, and at least one seeded listing exercises a multivalued Medium (e.g. a curio as Wood + Metal) so the FEAT-029 multivalued behavior stays demonstrated after Material's removal.
- [ ] The walnut table gains a **Wood** option axis (Walnut default, Oak) — the user-described pattern: attribute says Wood, the axis says which wood. Variants regenerate across Length × Width × Wood with the existing per-cell override prices carried to both wood options (same price per size unless a surcharge is more believable); publish validation stays satisfied.
- [ ] The engraved ring keeps its Metal axis and gets Medium = Metal — the same pattern from the jewelry side.
- [ ] Storefront filter, search, Highlights, and the listing Medium line all reflect the consolidated vocabulary after `make fresh` (`/?medium=wood` returns the table and the gnome; `ceramic` still returns the pottery).
- [ ] docs/item-configurator.md updated where it names the Material property or the old vocabulary (taxonomy examples, seeds notes).
- [ ] `make check` green; coverage 100%; journal updated.

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

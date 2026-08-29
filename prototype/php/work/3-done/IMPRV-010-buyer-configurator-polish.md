---
id: IMPRV-010
type: improvement
status: resolved
created: 2026-08-27
---

# IMPRV-010: Buyer configurator polish

## Problem
A hands-on walk of the seeded archetypes on `/art/{slug}` (post FEAT-027/028/029) found four presentation gaps, all buyer-facing:

1. **Unit picker order** — units sort by label as strings: #1, #10, #11, #12, #2, … (candlesticks archetype).
2. **Unit specs leak machine keys** — cards render `specs_json` raw: "height_mm: 205, weight_g: 310".
3. **Overridden price labelled "Base price"** — a variant with `price_override_cents` (walnut table 48×30) shows its $1,100 as "Base price"; the breakdown line should name the selected combination.
4. **Invisible keyboard focus on unit cards** — the card's radio is `sr-only`; arrowing between units gives no visible focus/selection cue until the form resubmits.

## Goal
The unit picker reads like a curated card grid and the price panel names what it prices, for keyboard users too.

## Outcome
- [x] Units order naturally: by `position`-free natural sort of `label` (numeric-aware, so #2 < #10) — pure domain or a well-tested comparator, applied everywhere units list (buyer picker and the seller units screen).
- [x] Unit specs render humanized: `height_mm: 205` → "Height: 205 mm", `weight_g: 310` → "Weight: 310 g"; unknown keys fall back to title-cased key with no unit; covered by a unit-tested formatter (pure domain), used by the buyer card and the seller units screen.
- [x] For a variant priced by override, the breakdown's first line labels the combination (e.g. "48 in / 30 in") instead of "Base price"; additive-priced variants keep "Base price" + per-option lines. The same label lands in `price_breakdown_json` at placement (extend the FEAT-028 smoke assertion if it pins the old label).
- [x] Unit cards show a visible ring when their radio has keyboard focus (CSS `:has(:focus-visible)` on the label or an equivalent no-JS mechanism) and the selected card stays visually distinct.
- [x] `make check` green; coverage 100%; journal updated.
- [x] (Addendum) The pet-portrait archetype's compound "Pets & Pose" axis is split into two independent axes, "Pets" and "Pose".

## Why it matters
The unit picker exists to beat Etsy's numbered-dropdown-plus-photo-gallery workaround; string-sorted numbers, raw column keys, and invisible focus give back the polish that justified the primitive.

## Discovery notes
- Buyer partial: resources/views/shop/partials/configurator.blade.php; seller units screen: resources/views/seller/listings/variants/units/index.blade.php.
- Breakdown assembly: app/Domain/Configurator/PriceBreakdown* and app/Support/Configurator/PriceBreakdownAssembler (FEAT-027) — the label change belongs where the first line is written, so cart, checkout, and snapshot inherit it.
- The FEAT-028 smoke walk asserts the frozen breakdown renders identically on customer/seller/admin views — keep that invariant; only the label text changes.
- Everything through make targets from prototype/php; physical goods only; no JS.

## Related work
- FEAT-027 (buyer configurator), FEAT-028 (snapshot), FEAT-029 (Highlights)

## Working
- `App\Domain\Configurator\UnitLabelOrder` — a tested `strnatcmp()` wrapper — is the one comparator both `ConfiguratorPageResolver::buildUnitsPresentation()` (buyer unit picker) and `Seller\UnitController::indexData()` (seller units screen) sort by; both fetch units by query (not the lazy-loaded relation) and sort the collection in PHP.
- `App\Domain\Configurator\UnitSpecLabel::format()` humanizes one `specs_json` entry: a trailing segment matching a known unit abbreviation (`mm`, `cm`, `g`, `kg`, `in`, `oz`, `lb`, `ft`) is split off and shown after the value; anything else title-cases the whole key with no unit. Used by `ConfiguratorPageResolver::specLines()` (buyer card, renamed presentation key `specs` → `specLines`) and inline in the seller units Blade view (a read-only preview line above the raw JSON edit field).
- `App\Domain\Configurator\OverridePriceLabel::forCombination()` joins the selected option labels with " / " (e.g. "48 in / 30 in"); with no axis selected (a serialized unit's own override, no axes at all) it falls back to "Base price". `ConfigurationPricer::price()` calls it only on the override branch, so cart, checkout snapshot, and every order-detail view inherit it from the one call site — no separate snapshot fix needed. Verified live: the walnut table's default combo now reads "36 in / 24 in" ($800.00) and the 48×30 combo reads "48 in / 30 in" ($1,100.00), both where "Base price" showed before.
- The buyer unit-card `<label>` gained `has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-neutral-900` (Tailwind v4's `:has()` variant, no JS) alongside the existing selected-border styling, now `ring-1 ring-neutral-900` in addition to `border-neutral-900` so the selected card stays visually distinct from a merely-focused one.
- Addendum: `ConfiguratorArchetypeSeeder::petPortrait()` now creates independent "Pets" (1 Pet / 2 Pets +$15.00) and "Pose" (Sitting / Playful) axes instead of the compound "Pets & Pose" value set, alongside the unchanged "Size & Framing" axis; `GenerateVariants` now produces the full 2×2×2 = 8-variant grid (previously 3×2 = 6, generated from three hand-picked "Pets & Pose" combinations). Effective prices are unchanged for every combination that existed before.
- Eyeballed over HTTP against `make fresh` seed data: `/art/vintage-brass-candlesticks-individually-listed` (units in `#1 … #12` order, humanized specs) and `/art/live-edge-walnut-dining-table` (override combination labels) and `/art/custom-pet-portrait` (split Pets/Pose axes).

### Numbers
`make check`: 2305 tests, 6594 assertions, 100% lines.

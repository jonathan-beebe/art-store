---
id: FEAT-026
type: feature
status: resolved
created: 2026-08-26
---

# FEAT-026: Seller configurator UI

## Problem
FEAT-025 gives a seller the primitives (axes, sparse variants, units, scoped modifiers, quantity breaks, sections) but no screens to use them — every write is action-only, driven from tests and seeders. `etsy-product-configuration.md` §2.2's case studies show what a seller does without a real UI for these primitives: hand-priced 136-cell matrices maintained off-platform, per-unit numbering re-plumbed by hand every time stock changes, config smuggled into a box meant for something else. This ticket is the seller-facing screens that make the FEAT-025 primitives usable.

## Goal
A seller configures anything from a zero-axis print to a multi-axis, serialized, or tiered listing entirely from the seller portal, sees the derived price of every variant before publishing, and gets inline, actionable validation before going live.

## Outcome
- [x] Axes/options screen nested under the existing seller listing edit flow: add an axis (catalog property or custom label), add values per axis with surcharge + default flag + position; edit and delete respect variants that reference them.
- [x] Sparse variant grid: rows only for combinations the seller has enabled (never a materialized Cartesian product), each row showing its derived price (`base + Σ surcharges`) beside an override field, plus SKU, quantity, enabled toggle per cell; bulk actions to enable/disable combinations by axis value. The platform sells physical goods only — no delivery-method field anywhere.
- [x] Unit management screen for variants marked serialized: add/edit units (label, state, condition note, specs, price override); quantity for a serialized variant is read-only, derived from `count(units where available)`.
- [x] Modifiers screen: add a modifier (kind, prompt, instructions, required, position, pricing fields per kind), add modifier options for `select` kind, and a scope picker — "show this question only when …" listing the listing's option values, empty selection meaning always-shown.
- [x] Quantity breaks screen: add/edit/remove `(min_qty, discount_bps)` tiers, ≤10 enforced inline.
- [x] Description sections screen: add/reorder/remove typed sections (`text`, `specs`, `size_chart`, `faq`, `care`, `disclaimer`), ≤15 enforced inline.
- [x] Publish validation (the `Draft → ForSale` transition) surfaces every issue from FEAT-025's validation function inline on the relevant screen — a variant-pricing issue links to the variant grid, a missing-unit issue links to the unit screen, etc. — rather than one undifferentiated error.
- [x] Every write route: a `FormRequest` under `Http/Requests/Seller` authorizing ownership (wrong seller's listing → 404, matching the existing `ListingPolicy` pattern), the `listing_write` rate limit, and a `listing.update` (or `listing.create`) log line via the Action from FEAT-025 — no controller performs a write directly against a model.
- [x] Routes follow the existing nested-resource pattern (`Route::resource('listings.faqs')->scoped()` in `routes/seller.php` is the model to extend, not replace).
- [x] HTTP feature tests walking each screen's create/edit/delete and the publish-validation redirect-with-errors path; sidecar test per new class; `make check` green; coverage 100%.
- [x] `prototype/php/work/journal.md` updated: FEAT-026 defined/started/done lines.

## Why it matters
The design doc's seller flow (§4) exists specifically so a seller never has to fake an axis inside a personalization dropdown or hand-maintain a spreadsheet of variant prices outside the tool — the grid showing derived price per row is what makes "the price on screen is the price at checkout" true from the seller's side too.

## Discovery notes
- Read `prototype/php/docs/architecture.md` §"Authorization" and §"Refusals" — ownership denials are `Response::denyAsNotFound()`; a `DomainRuleViolation` is mapped once, globally, to `back()->withInput()->withErrors(...)`, so these screens add no local error-copy of a guard the Action already holds.
- `app/Http/Controllers/Seller/ListingController.php`, `ListingFaqController.php`, and `ListingStatusController.php` are the closest existing shapes: nested-resource controller, `Route::resource(...)->scoped()`, and a status-transition endpoint that surfaces a `DomainRuleViolation`, respectively.
- `docs/alignment.md` §3: seller configurator writes reuse the existing `listing_write` rate limit (`RATE_LIMIT_LISTING_WRITE`, keyed by seller id) — no new limit name.
- Log events are closed per `docs/alignment.md` §2.3 — every write here rides `listing.update`/`listing.create`/`listing.publish`, never a new event name.
- Blade views use `<x-form.field>` components and Tailwind v4, per the existing seller portal screens — match the existing dense, tool-focused seller theme (`prototype/php/docs/architecture.md` §"Sites").
- Caps enforced here (70 options/axis, 500 variants, 5 modifiers, 10 quantity tiers, 15 sections) must produce the same refusal shape FEAT-025's publish validation produces — this ticket surfaces those issues, it does not duplicate the rule.
- Risk: the variant grid for a two-axis product with high cardinality (walnut table: 17×8) must stay usable without materializing all 136 combinations by default — build from the seller's enabled rows plus an explicit "generate combinations" bulk action, not an eagerly rendered full grid.

## Working

Ten seller-facing screens, nested under the listing edit flow, all Blade + plain forms (JS off works throughout):

- Axes & options — `seller.listings.option-axes.{index,store,update,destroy}` + `seller.listings.option-axes.option-values.{store,update,destroy}`
- Variant grid — `seller.listings.variants.{index,store,update}` + `seller.listings.variants.generate` + `seller.listings.variants.bulk`
- Units (per serialized variant) — `seller.listings.variants.units.{index,store,update}`
- Modifiers — `seller.listings.modifiers.{index,store,update,destroy}` + `seller.listings.modifiers.scope` + `seller.listings.modifiers.options.{store,update,destroy}`
- Quantity breaks — `seller.listings.quantity-breaks.{index,store,update,destroy}` (≤10 tiers enforced inline against `ConfiguratorPublishValidation::MAX_QUANTITY_TIERS`)
- Description sections — `seller.listings.description-sections.{index,store,update,destroy}` + `.reorder` (≤15 sections enforced inline; reorder swaps with a neighbor through a sentinel position, dodging the `(listing_id, position)` unique index)

Every write: a `FormRequest` authorizing via `Gate::inspect('update', $listing)`, the `listing_write` rate limit through `RateLimitGate` (429 re-renders the same index view via `Controller::tooManyRequests()`), and an Action from FEAT-025 or a new one added here. Three-level nested resources (`listings.option-axes.option-values`, `listings.variants.units`, `listings.modifiers.options`) resolve through Laravel's `->scoped()` binding because their segment names (`option-axes`, `option-values`, `units`, `options`) singularize to the exact relation names FEAT-025's models already carry (`optionAxes`, `optionValues`, `units`, `options`) — no binding-field overrides needed. The two hand-written two-model routes (`modifiers/{modifier}/scope`, `description-sections/{section}/reorder`) needed an explicit `->scopeBindings()`, since `Route::resource(...)->scoped()` doesn't cover routes declared outside a resource — without it, a seller's own listing id paired with a different listing's modifier/section id would have bound successfully.

Publish validation: `Listing::publishIssues()` folds axes/variants/modifiers/quantity-breaks/sections into `ConfiguratorPublishValidation::check()`'s primitives. `ListingStatusController` runs it before a `Draft → ForSale` transition; a non-empty result logs a `refused` line and redirects to the listing edit screen, which independently (re)computes and renders the same list any time the listing is a draft — so the issues are visible whether or not a publish was just attempted. Each issue links to its owning screen via a `match` on `PublishIssue->code`; the three per-variant issues link straight to the offending variant (grid anchor or its unit screen) using a new `subjectId` on `PublishIssue`.

### Numbers

Before (FEAT-025 baseline): 1948 tests, 5628 assertions, 100% lines.
After: 2101 tests, 6043 assertions, 100% lines. `make check` green (Pint, PHPStan level max/no baseline, full coverage gate).

New: 16 Actions, 1 domain guard (`ConfiguratorDeletionGuard`), 1 domain enum (`DescriptionSectionMove`), 11 controllers, 14 `FormRequest`s, 13 Blade views (6 new directories + `edit.blade.php` extended), ~30 new routes.

### Deviations

- **`PublishIssue` gained an optional `subjectId`.** FEAT-025's `PublishIssue::of($code, $message)` carried no machine-readable reference to the row an issue is about — only a message with the id embedded as prose. Linking a variant-pricing or missing-unit issue to its exact row (rather than the general screen) needed one; added as a third, defaulting-null constructor argument, so every existing call site and test kept compiling unchanged.
- **16 new Actions** for the writes FEAT-025 shipped no update/delete/bulk path for: `UpdateOptionAxis`, `DeleteOptionAxis`, `UpdateOptionValue`, `DeleteOptionValue`, `SetVariantsEnabledByOptionValue` (bulk toggle by axis value), `UpdateUnit`, `UpdateModifier`, `DeleteModifier`, `UpdateModifierOption`, `DeleteModifierOption`, `SetModifierScope` (the picker's full-replace semantics — `ScopeModifier` from FEAT-025 stayed as its additive-only counterpart, still used by the seeder), `UpdateQuantityBreak`, `DeleteQuantityBreak`, `UpdateDescriptionSection`, `DeleteDescriptionSection`, `ReorderDescriptionSection`. Each mirrors an existing Add/Create action's shape (`Story::for(StoryEvent::ListingUpdate)`).
- **`ConfiguratorDeletionGuard`** (new domain class): deleting an axis or option value cascades at the DB level even while a variant still selects it (`variant_options` FKs are `cascadeOnDelete()`), which would silently strip a variant's axis coverage. Guarded at the application layer with a `DomainRuleViolation`, refusing the delete instead — the schema's own permissiveness was the gap the ticket's "edit and delete respect variants that reference them" bullet was written against.

### Doc contradiction found

`docs/architecture.md` §6 (Publish validation) lists "Every required `category_properties` grant has a matching `listing_attributes` row" as a gate on the `Draft → ForSale` transition. `ConfiguratorPublishValidation::check()` (FEAT-025) never implemented that gate — its signature carries no attribute/requirement data at all. Per this ticket's own instruction not to duplicate a rule the domain validation doesn't hold, no screen surfaces it. Separately, `docs/item-configurator.md` §4's flow diagram opens the seller flow with "Pick category (gates which properties are offered below)," but no ticket Outcome bullet asks for a category-picker screen, and no seller-portal screen (this ticket or earlier ones) ever sets `listings.category_id`. The axes screen's catalog-property picker is wired and works, but is currently unreachable in practice — every listing's category stays null until a future ticket adds that screen.

## Related work
- FEAT-025 (data model, domain pricing, and the Actions this UI calls)
- FEAT-027 (buyer configurator + cart)
- FEAT-028 (checkout + order snapshot)
- `__local__/item-configuration/etsy-product-configuration.md`
- `__local__/item-configuration/etsy-product-configuration-design-doc.md`
- `docs/alignment.md` §3 (rate limits), §2 (logging)

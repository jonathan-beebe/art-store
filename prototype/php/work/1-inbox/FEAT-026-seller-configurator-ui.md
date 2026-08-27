---
id: FEAT-026
type: feature
status: open
created: 2026-08-26
---

# FEAT-026: Seller configurator UI

## Problem
FEAT-025 gives a seller the primitives (axes, sparse variants, units, scoped modifiers, quantity breaks, sections) but no screens to use them — every write is action-only, driven from tests and seeders. `etsy-product-configuration.md` §2.2's case studies show what a seller does without a real UI for these primitives: hand-priced 136-cell matrices maintained off-platform, per-unit numbering re-plumbed by hand every time stock changes, config smuggled into a box meant for something else. This ticket is the seller-facing screens that make the FEAT-025 primitives usable.

## Goal
A seller configures anything from a zero-axis print to a multi-axis, serialized, or tiered listing entirely from the seller portal, sees the derived price of every variant before publishing, and gets inline, actionable validation before going live.

## Outcome
- [ ] Axes/options screen nested under the existing seller listing edit flow: add an axis (catalog property or custom label), add values per axis with surcharge + default flag + position; edit and delete respect variants that reference them.
- [ ] Sparse variant grid: rows only for combinations the seller has enabled (never a materialized Cartesian product), each row showing its derived price (`base + Σ surcharges`) beside an override field, plus SKU, quantity, enabled toggle per cell; bulk actions to enable/disable combinations by axis value. The platform sells physical goods only — no delivery-method field anywhere.
- [ ] Unit management screen for variants marked serialized: add/edit units (label, state, condition note, specs, price override); quantity for a serialized variant is read-only, derived from `count(units where available)`.
- [ ] Modifiers screen: add a modifier (kind, prompt, instructions, required, position, pricing fields per kind), add modifier options for `select` kind, and a scope picker — "show this question only when …" listing the listing's option values, empty selection meaning always-shown.
- [ ] Quantity breaks screen: add/edit/remove `(min_qty, discount_bps)` tiers, ≤10 enforced inline.
- [ ] Description sections screen: add/reorder/remove typed sections (`text`, `specs`, `size_chart`, `faq`, `care`, `disclaimer`), ≤15 enforced inline.
- [ ] Publish validation (the `Draft → ForSale` transition) surfaces every issue from FEAT-025's validation function inline on the relevant screen — a variant-pricing issue links to the variant grid, a missing-unit issue links to the unit screen, etc. — rather than one undifferentiated error.
- [ ] Every write route: a `FormRequest` under `Http/Requests/Seller` authorizing ownership (wrong seller's listing → 404, matching the existing `ListingPolicy` pattern), the `listing_write` rate limit, and a `listing.update` (or `listing.create`) log line via the Action from FEAT-025 — no controller performs a write directly against a model.
- [ ] Routes follow the existing nested-resource pattern (`Route::resource('listings.faqs')->scoped()` in `routes/seller.php` is the model to extend, not replace).
- [ ] HTTP feature tests walking each screen's create/edit/delete and the publish-validation redirect-with-errors path; sidecar test per new class; `make check` green; coverage 100%.
- [ ] `prototype/php/work/journal.md` updated: FEAT-026 defined/started/done lines.

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

## Related work
- FEAT-025 (data model, domain pricing, and the Actions this UI calls)
- FEAT-027 (buyer configurator + cart)
- FEAT-028 (checkout + order snapshot)
- `__local__/item-configuration/etsy-product-configuration.md`
- `__local__/item-configuration/etsy-product-configuration-design-doc.md`
- `docs/alignment.md` §3 (rate limits), §2 (logging)

---
id: BUG-005
type: bug
status: open
created: 2026-08-27
---

# BUG-005: Adding an existing variant combination answers a 500

## Problem
On the seller variant grid, submitting "Add variant" for a combination that already has a row throws `UniqueConstraintViolationException` on `(variants.listing_id, combo_key)` and renders a raw 500. Reproduced on the pet portrait, whose grid holds every combination, so any add collides. `CreateVariantRequest` validates each axis value individually; nothing validates the combination, and `CreateVariant` inserts bare. The log line is a `failed` (defect) when a duplicate combination is an expected domain "no" (`refused`).

## Goal
A duplicate combination is refused in two layers — the form does not offer what cannot be added, and the server turns the attempt into inline, actionable form feedback — and a 500 is impossible for this path.

## Outcome
- [ ] Layer A — the form: when every axis combination already has a variant row, the add-variant form is replaced server-side by a plain note ("Every combination exists — edit rows above."); when combinations remain, the form renders as today. No JS.
- [ ] Layer B — the server: `CreateVariantRequest` gains a combination-uniqueness rule (compute the combo key from the submitted values, refuse when a variant with it exists) with the message naming the combination and what to do ("Gold / Both Sides already exists — edit its row in the grid above."); the re-rendered form shows it inline via the existing error mechanics.
- [ ] `CreateVariant` (the action) guards the same rule as a `DomainRuleViolation` — the race-and-non-HTTP-caller backstop — so the log line becomes `listing.update` `refused` with a reason, never `failed`; the global handler renders it as form feedback if it ever fires past the request rule.
- [ ] A regression test per layer: the full-grid form state, the request rule's inline message, the action's refusal (including that no 500 and no `failed` line occurs).
- [ ] `make check` green; coverage 100%; journal updated.

## Why it matters
The variant grid is the configurator's core seller surface; its most common edge (re-adding a combination) currently crashes instead of guiding.

## Discovery notes
- app/Http/Requests/Seller/CreateVariantRequest.php (per-axis `Rule::exists` is the only guard today), app/Actions/Configurator/CreateVariant.php:38-47, app/Domain/Configurator/ComboKey.php, resources/views/seller/listings/variants/index.blade.php (the add form).
- The refusal shape: `DomainRuleViolation` maps globally to `back()->withInput()->withErrors(...)` (docs/architecture.md §Refusals) — the action guard needs no controller code.
- GenerateVariants already skips existing combinations — the single-add path is the gap.
- Coordinate with FEAT-031's landing: the pet portrait/table grids regenerate there; write tests against fixture listings, not seeded ones.

## Related work
- FEAT-026 (variant grid), FEAT-031 (grid regeneration in seeds), BUG-006 (error page styling — the page this bug currently renders)

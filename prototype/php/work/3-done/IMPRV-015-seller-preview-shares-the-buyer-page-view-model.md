---
id: IMPRV-015
type: improvement
status: resolved
created: 2026-08-28
---

# IMPRV-015: The seller's buyer preview shares the buyer page's view model and behavior

## Problem
The "What buyers see" panel (resources/views/components/seller/buyer-view.blade.php)
is a hand-maintained approximation of the shop listing page: it renders a
subset (no images, no description), stays inert (form fields do nothing), and
duplicates the shop page's markup branch by branch — BUG-012 had to edit the
same rendering rule in two files, and BUG-013 existed because the panel's
content drifted from what the buyer page actually shows.

## Goal
The buyer page and the seller's buyer preview share the same view model and
the same behavior, differing only in rendering details. The preview is
complete — images, title, description, and a working form whose options
interact — and as close to the real thing as possible.

## Outcome
- [x] One view model feeds both `/art/{slug}` and the seller preview panel:
      the resolved configuration, option availability/grey-outs, unit cards,
      scoped questions, price breakdown, images, title, description, and
      stock state come from the same code path, not parallel Blade logic.
- [x] The preview shows the listing's images, title, and description as the
      buyer page presents them.
- [x] The preview's form works: changing an option, unit, quantity, or
      answer updates the availability, grey-outs, and total the same way the
      buyer page does (IMPRV-011's auto-update behavior included; the no-JS
      "Update options" path works on seller screens too).
- [x] Rendering differences are deliberate and small: the panel frame
      ("What buyers see" caption), scale/layout to fit the 380px column, and
      an inert Add to cart (a preview never mutates a cart).
- [x] The shared path is covered so a rendering-rule change lands in both
      surfaces from one edit (BUG-012's class of two-file fix is gone).
- [x] The eight seller screens' hand-duplicated panel embed (the
      `grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_380px]` wrapper
      plus the panel slot) collapses into one layout component while the
      embeds are being touched.
- [x] `make check` green; coverage 100%; journal updated.

## Why it matters
The panel is the redesign's core reassurance. Every divergence between it
and the real buyer page is a lie waiting to be reported (BUG-012, BUG-013
were both drift of exactly this kind), and every rendering rule maintained
twice doubles the cost of touching the buyer experience.

## Discovery notes
- The shop page's data assembly (ListingController + ConfiguratorPageResolver
  and friends) is the candidate single source; the seller screens would
  build the same view model for the listing being edited, driven by the same
  GET params so the preview's form round-trips on the seller page's own URL.
- Known duplicated blocks between the shop configurator and the panel:
  the option-select branch, the price-breakdown `<dl>`, and the
  serialized-unit cards (from BUG-011/BUG-012's findings; this ticket
  subsumes the 0-refine shared-view-partials research note, discarded
  2026-08-28).
- The preview embeds a form inside seller pages that already carry their own
  forms — nesting and CSRF/GET interplay need care (the shop configurator is
  one form with two submits; a nested form inside a seller form is invalid
  HTML — placement or `form=` attributes need deciding).
- Eight seller surfaces embed the panel (hub, six sections, Basics); the
  live-form behavior must work on each.

## Related work
- IMPRV-011 (auto-update behavior to share), BUG-011/012/013 (drift the
  shared model removes), DSGN-002 (panel origin), IMPRV-013 (hub panel)

## Working

### Plan (written before coding)

**View model.** `ConfiguratorPageResolver::resolve()` / `hasConfigurator()`
are already the one code path both surfaces call (BuyerView already used
them) — no new DTO. What was actually duplicated is the *rendering*: two
hand-written Blade files drew the axis selects, unit cards, modifier
inputs, quantity-tier table, and price breakdown twice. That duplication is
what collapses.

**Shared partials (new/rewritten):**
- `shop/partials/configurator.blade.php` — rewritten to take a `mode` prop:
  `'shop' | 'preview' | 'static'`. Same markup and classes in every mode
  (axis selects, unit cards, modifiers, quantity-tier `<table>`, price
  `<dl>`) — mode only changes: the form's method/action/CSRF, hidden fields
  that round-trip the current URL's non-configurator query params (preview
  only, since a GET submit replaces the whole query string), whether
  controls carry `disabled` (`static` only), and how Update-options/Add-to-
  cart render (real buttons in `shop`; Update-options a real GET submit but
  Add-to-cart an inert span in `preview`; both inert in `static`).
- `shop/partials/listing-images.blade.php` and
  `shop/partials/listing-description.blade.php` (new) — cover + thumbnail
  images; description paragraph + description sections. Used by both
  `shop/listing.blade.php` and the buyer-view panel, `$compact` toggling
  size for the 380px column. (Split in two rather than one "listing-summary"
  partial once shop/listing.blade.php's actual layout turned out to
  interleave title/price/dl/highlights between them — title stayed a
  one-line inline in each caller instead of a third partial, since a single
  `<h1>`/`<p>` has nothing to duplicate.)
- `shop/partials/add-to-cart-button.blade.php` (new) — the one control the
  ticket names as a deliberate difference (real submit vs inert), shared by
  the shop page's unconfigured branch, the shared configurator partial, and
  the panel's own unconfigured branch.

**BuyerView component** gains: `$refreshUrl = request()->url()` (the
seller screen's own URL, no query — the shared partial's preview-mode form
posts GET back to exactly this), `$focusId` from `request()->query('focus')`
(same continuity mechanism the shop page uses), and
`$listing->loadMissing(['images', 'descriptionSections'])` ordered by
position, so no seller controller needs new eager-loading wiring for the
newly-visible summary content under `Model::shouldBeStrict`. New
`bool $interactive = true` constructor param selects `preview` vs `static`
mode.

**Form placement.** Every one of the 8 embeds already places
`<x-seller.buyer-view>` in a `<div>` that is a *sibling* of the left
column's own `<form>` elements, never nested inside one — confirmed by
reading all 8 templates before writing anything. So the preview's own
`<form method="GET">` needs no `form=` attribute trick, just correct
placement (which it already has). Non-configurator query params live on
the URL already (`mode`, `choice` on option-axes; `kind` on modifiers and
description-sections; `edit` on units) — the shared partial preserves them
as hidden fields in `preview` mode by reading `request()->query()` minus
the reserved configurator keys (`axis`, `unit`, `modifier`, `quantity`,
`focus`), so submitting the live preview form doesn't silently drop them.

**Layout component.** `components/seller/editor-layout.blade.php`
(anonymous, `x-seller.editor-layout`): default slot is the left column
(`flex flex-col gap-4`), `panel` slot is the right column, grid fixed at
`lg:grid-cols-[1fr_380px]` — replaces the 8 screens' hand-copied
`grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_Npx]` wrapper (widths
had drifted to 380/400/420/24rem across screens; standardizing on 380px is
the ticket's own stated target).

**Design fork, recorded per the hard rules.** `ModifierController`'s
scope-demo shows two buyer-view panels side by side ("applies" / "other"),
each pinned to a specific option value by `ScopedListingPreview::resolve`
— a pure function of the modifier's stored scope, never the request query.
Making both panels real interactive forms would accept a seller's clicks
and then silently discard them on the next render (the controller has no
mechanism to tell which of the two panels changed, or to feed that back
into `ScopedListingPreview`). That is worse than the old disabled
rendering. Decision: this one embed passes `:interactive="false"` (the
shared partial's `static` mode — same markup, disabled controls, inert
everything); every other embed's default single panel is fully live. This
is the one seller-visible behavior difference beyond the ticket's named
list (frame/caption/scale/inert-add-to-cart), made because "the preview is
the real thing" cannot honestly apply to a form whose submission is
guaranteed to be ignored.

**Seller controller changes** (minimal — most screens need none, since
BuyerView's own default `ConfiguratorInput::fromQuery(request())` makes the
plain panel live automatically):
- `UnitController::indexData` — pins default axis selections to the
  variant being managed via `ConfiguratorInput::fromQuery($request,
  defaultAxisSelections: ..., defaultQuantity: 1)`, so the panel opens on
  the right combination and stays live for unit/quantity afterward.
- `quantity-breaks/index.blade.php`'s own `@php` block (no controller
  change) switches its fixed `ConfiguratorInput::of(...)` to
  `ConfiguratorInput::fromQuery(request(), defaultQuantity: $previewQuantity)`
  the same way.
- `ConfiguratorInput::fromRaw`/`fromQuery` gain optional
  `$defaultAxisSelections` / `$defaultUnitId` / `$defaultQuantity`
  parameters (default `[]`/`null`/`1`, so the shop page's existing calls
  are untouched).
- `DescriptionSectionController`'s screen currently hand-rolls its own
  mini preview (title + sections only, no `x-seller.buyer-view` at all) —
  replaced with the real component now that it renders sections too.

**Autosubmit script.** Add `configurator-autosubmit.js` to
`components/layouts/seller.blade.php` (it already self-guards on
`[data-configurator]`, so it's inert on seller pages with no live panel).

### What landed

Files changed, grouped:

- **Shared view/rendering** — `App\Support\Configurator\ConfiguratorInput`
  (`fromRaw`/`fromQuery` gained optional default-axis/unit/quantity params,
  back-compat); `App\View\Components\Seller\BuyerView` (rewritten:
  `$refreshUrl`, `$focusId`, `loadMissing` on `images`/`descriptionSections`,
  `bool $interactive`).
- **Blade partials (new)** — `shop/partials/listing-images.blade.php`,
  `shop/partials/listing-description.blade.php`,
  `shop/partials/add-to-cart-button.blade.php`,
  `components/seller/editor-layout.blade.php`.
- **Blade partials (rewritten)** — `shop/partials/configurator.blade.php`
  (single file, `mode` prop: `shop` | `preview` | `static`),
  `components/seller/buyer-view.blade.php` (frame + compact title only now).
- **Shop page** — `shop/listing.blade.php` (uses the two shared partials and
  the shared configurator partial in `shop` mode; output byte-identical per
  the full `ListingControllerTest` suite staying green unchanged).
- **Seller screens (all 8 embeds)** — `seller/listings/edit.blade.php`,
  `seller/listings/basics/edit.blade.php`,
  `seller/listings/option-axes/index.blade.php`,
  `seller/listings/variants/index.blade.php`,
  `seller/listings/variants/units/index.blade.php`,
  `seller/listings/modifiers/index.blade.php` (the scope-demo pair now
  passes `:interactive="false"`), `seller/listings/quantity-breaks/index.blade.php`
  (its own `@php` block switched to `ConfiguratorInput::fromQuery`),
  `seller/listings/description-sections/index.blade.php` (dropped its
  hand-rolled preview for the real component).
- **Seller controllers** — `App\Http\Controllers\Seller\UnitController`
  (`indexData` threads `$request` into `ConfiguratorInput::fromQuery` with
  the variant's own axis selections as the default).
- **Layout** — `components/layouts/seller.blade.php` ships
  `configurator-autosubmit.js`.
- **Docs** — `docs/item-configurator.md`'s hub screen note rewritten for the
  live panel and the one static exception.

Tests added or changed, by file:

- `App\Support\Configurator\ConfiguratorInputTest` — `it falls back to the
  given defaults when the request carries no axis, unit, or quantity`,
  `it prefers the request over the given defaults once the request carries
  a value`.
- `App\View\Components\Seller\BuyerViewTest` — replaced `renders no live
  form and no submit action for a shop route` with `IMPRV-015: renders a
  live GET form that round-trips on the seller URL, never the cart route`;
  rewrote `B1`/`B2` for the dropped label-only hint (`B1: carries the char
  limit as a maxlength, same as the shop page`, `B2: shows the flat charge
  in the price breakdown once answered, same as the shop page`); rewrote
  `C3` for the shared `<table>` markup.
- `App\Http\Controllers\Seller\ModifierControllerTest` — rewrote `B1` for
  the dropped hint; added `IMPRV-015: the scoped-preview pair stays
  disabled rather than falsely interactive`, `IMPRV-015: the default buyer
  panel (no scoped question yet) is a live, enabled form`.
- `App\Http\Controllers\Seller\QuantityBreakControllerTest` — rewrote `C3`
  for the shared `<table>` markup.
- `App\Http\Controllers\Seller\OptionAxisControllerTest` — added
  `IMPRV-015: the buyer panel round-trips a live option pick on this
  screens own URL`, `IMPRV-015: the buyer panel preserves this screens own
  query params across a live refresh`, `IMPRV-015: ships the configurator
  auto-submit script on a seller listing screen`.

Every rewritten/dropped assertion changed because the underlying rendering
deliberately changed (panel became live, or dropped panel-only markup the
real buyer page never showed) — none were weakened to paper over a
regression.

Full suite: 2734 passed (7799+ assertions after the additions above).
Coverage: 100% (`make coverage`). `make lint` (Pint + PHPStan): clean.
`make check`: green.

Left for a human browser walk: visually confirm the panel's 380px column
still reads well now that it carries full-size shop-page classes (padding/
font sizes were deliberately left unified rather than given a second,
compact class set — see the partial-sharing note above); confirm the
auto-submit behavior feels right on a seller screen with a mouse (focus
return after a refresh, no visible flash).

---
id: IMPRV-011
type: improvement
status: open
created: 2026-08-27
---

# IMPRV-011: The configurator auto-updates on change

## Problem
On `/art/{slug}`, changing a configurator control does nothing until the shopper presses "Update options" — change Length and the Width options, grey-outs, unit cards, scoped questions, and the price panel all show the previous selection's truth until the extra click. The shopper has to know the button exists and press it after every single choice.

## Goal
A selection immediately refreshes the rest of the form — available options, unit cards, scoped questions, price panel — with no "Update options" press; shoppers without JavaScript keep exactly today's flow.

## Outcome
- [x] Progressive enhancement only: a small dependency-free script auto-submits the configurator's GET form when an axis select, unit card, or quantity changes; with JavaScript off, the form and its "Update options" button behave byte-for-byte as today. Nothing may function only with JS — the platform contract stands.
- [x] When the script is active, the "Update options" button is hidden (script-applied, so it renders for no-JS shoppers).
- [x] Typed modifier answers survive the refresh (they already round-trip through the GET params today — a test pins that this stays true), and quantity changes debounce enough that typing "150" doesn't fire three refreshes (submit on change/blur for the number input, not per keystroke).
- [x] Continuity: after the refresh the page doesn't feel reset — the changed control regains focus and the configurator stays in view (URL fragment, autofocus, or equivalent server-rendered mechanism; decide and record). A full-page navigation that meets this is acceptable; an in-place fetch-and-swap of the configurator and price panel is equally acceptable if it stays small, dependency-free, and falls back cleanly.
- [x] The script ships like the existing live-badge.js (public/, `<script defer>`, no build step) or inline per the storefront's pattern — match whatever keeps the asset story simplest; CSP must not need widening in production.
- [x] Feature tests cover the no-JS path unchanged; a browser-level check of the enhanced path is manual (record the walk in the ticket's Working section).
- [x] `make check` green; coverage 100%; journal updated.

## Why it matters
"The price on screen is the price at checkout" only lands if the screen is current; a stale panel behind a button the shopper must rediscover after every choice is the design's weakest moment in practice.

## Discovery notes
- resources/views/shop/partials/configurator.blade.php — one form, two submits (formmethod GET "Update options" / POST add-to-cart); the auto-submit must target the GET action explicitly (`form.requestSubmit(updateButton)`) so it never accidentally posts to cart.
- public/live-badge.js is the precedent for a no-build vanilla script; SecurityHeaders' production CSP is `default-src 'self'` — an external same-origin script file passes, inline script does not.
- The unit picker radios and axis selects both need the change listener; the quantity number input wants change (not input) semantics.
- IMPRV-010's focus-visible ring on unit cards must still show after a refresh-driven re-render.

## Related work
- FEAT-027 (buyer configurator), IMPRV-010 (unit picker polish)

## Working
- `public/configurator-autosubmit.js` — a self-guarding vanilla script (queries `[data-configurator]`, no-ops where none exists) shipped exactly like `live-badge.js`: `<script defer src="{{ asset(...) }}">` added to `resources/views/components/layouts/shop.blade.php`, no build step, external and same-origin so the production CSP (`default-src 'self'`) needs no widening. It listens for one bubbled `change` event on the form and only acts on a target carrying `data-configurator-refresh` (the axis selects, the unit radios, and the quantity input — not the modifier fields, which never change another control's options), then calls `form.requestSubmit(updateButton)` where `updateButton` is the element carrying `data-configurator-update` — the existing `formmethod="GET"` button — so the script can never trigger the form's own POST to cart. On init it hides that button (`updateButton.hidden = true`), a script-applied change so a no-JS shopper still sees and can use it.
- Continuity decision: **autofocus, server-rendered, no fetch/swap.** Before invoking `requestSubmit`, the script writes the id of the changed control into a hidden `<input type="hidden" name="focus" data-configurator-focus>` already in the form, so it round-trips through the GET query string like every other configurator field. `ListingController` reads `focus` off the query string (`is_string($request->query('focus')) ? $focus : null`, mirroring `ConfiguratorInput`'s own tolerance of a tampered request) and hands it to the view as `focusId`; the configurator partial renders `autofocus` on whichever axis `<select>`, unit `<input type="radio">`, or quantity `<input>` has a matching `id` (`axis-{id}` / `unit-{id}` / `quantity`). Autofocus both refocuses the control and scrolls it into view on the fresh page load, so a plain full-page GET navigation satisfies "the configurator stays in view" with no added JS and no URL fragment. Unit radios gained an `id="unit-{id}"` attribute for this (they had none before); everything else already carried the id it needed. The hidden `focus` field also rides along on the POST add-to-cart submit and on a no-JS "Update options" click, both harmlessly (`AddToCartRequest`'s validation rules don't mention it, and an empty value never matches an element's id).
- IMPRV-010's `has-[:focus-visible]:ring-2` ring on the unit-card `<label>` is untouched — only attributes were added to the radio and select elements, not the class list — so it keeps showing under real keyboard focus after a refresh-driven re-render.
- Feature tests (`App\Http\Controllers\Shop\ListingControllerTest`): `ships the configurator auto-submit script on the listing page`, `keeps a typed modifier answer on the page after a GET refresh` (the round-trip pin), `autofocuses the axis select named by the refresh, so a shopper does not lose their place`, `renders no autofocus on any control when nothing named the refresh`. The full existing no-JS suite (add-to-cart, axis/unit/modifier/quantity round-tripping, the legacy zero-axis listing) passed unchanged, pinning that the plain form still works byte-for-byte.
- Manual verification pending: the enhanced (JS-on) path — changing an axis/unit/quantity control auto-refreshes with no click, the "Update options" button visually disappears, typing a quantity fires once on blur/change rather than per keystroke, and focus visibly lands back on the changed control — has not been driven in a real browser in this session and needs a human walkthrough on `/art/{slug}` for a listing with axes, a serialized (unit-picker) listing, and a listing with quantity tiers.
- Refactor note (not done here): the axis-select and quantity-input `@if (...) autofocus @endif` conditionals render a stray double space when false (`name="axis[...]"  data-configurator-refresh`) — harmless HTML, but a Blade `@once`/helper or reordering the attributes would tidy it if this partial gets touched again.

### Numbers
`make check`: 2725 tests, 7774 assertions, 100% lines.

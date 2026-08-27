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
- [ ] Progressive enhancement only: a small dependency-free script auto-submits the configurator's GET form when an axis select, unit card, or quantity changes; with JavaScript off, the form and its "Update options" button behave byte-for-byte as today. Nothing may function only with JS — the platform contract stands.
- [ ] When the script is active, the "Update options" button is hidden (script-applied, so it renders for no-JS shoppers).
- [ ] Typed modifier answers survive the refresh (they already round-trip through the GET params today — a test pins that this stays true), and quantity changes debounce enough that typing "150" doesn't fire three refreshes (submit on change/blur for the number input, not per keystroke).
- [ ] Continuity: after the refresh the page doesn't feel reset — the changed control regains focus and the configurator stays in view (URL fragment, autofocus, or equivalent server-rendered mechanism; decide and record). A full-page navigation that meets this is acceptable; an in-place fetch-and-swap of the configurator and price panel is equally acceptable if it stays small, dependency-free, and falls back cleanly.
- [ ] The script ships like the existing live-badge.js (public/, `<script defer>`, no build step) or inline per the storefront's pattern — match whatever keeps the asset story simplest; CSP must not need widening in production.
- [ ] Feature tests cover the no-JS path unchanged; a browser-level check of the enhanced path is manual (record the walk in the ticket's Working section).
- [ ] `make check` green; coverage 100%; journal updated.

## Why it matters
"The price on screen is the price at checkout" only lands if the screen is current; a stale panel behind a button the shopper must rediscover after every choice is the design's weakest moment in practice.

## Discovery notes
- resources/views/shop/partials/configurator.blade.php — one form, two submits (formmethod GET "Update options" / POST add-to-cart); the auto-submit must target the GET action explicitly (`form.requestSubmit(updateButton)`) so it never accidentally posts to cart.
- public/live-badge.js is the precedent for a no-build vanilla script; SecurityHeaders' production CSP is `default-src 'self'` — an external same-origin script file passes, inline script does not.
- The unit picker radios and axis selects both need the change listener; the quantity number input wants change (not input) semantics.
- IMPRV-010's focus-visible ring on unit cards must still show after a refresh-driven re-render.

## Related work
- FEAT-027 (buyer configurator), IMPRV-010 (unit picker polish)

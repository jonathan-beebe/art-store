---
id: IMPRV-015
type: improvement
status: open
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
- [ ] One view model feeds both `/art/{slug}` and the seller preview panel:
      the resolved configuration, option availability/grey-outs, unit cards,
      scoped questions, price breakdown, images, title, description, and
      stock state come from the same code path, not parallel Blade logic.
- [ ] The preview shows the listing's images, title, and description as the
      buyer page presents them.
- [ ] The preview's form works: changing an option, unit, quantity, or
      answer updates the availability, grey-outs, and total the same way the
      buyer page does (IMPRV-011's auto-update behavior included; the no-JS
      "Update options" path works on seller screens too).
- [ ] Rendering differences are deliberate and small: the panel frame
      ("What buyers see" caption), scale/layout to fit the 380px column, and
      an inert Add to cart (a preview never mutates a cart).
- [ ] The shared path is covered so a rendering-rule change lands in both
      surfaces from one edit (BUG-012's class of two-file fix is gone).
- [ ] The eight seller screens' hand-duplicated panel embed (the
      `grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_380px]` wrapper
      plus the panel slot) collapses into one layout component while the
      embeds are being touched.
- [ ] `make check` green; coverage 100%; journal updated.

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

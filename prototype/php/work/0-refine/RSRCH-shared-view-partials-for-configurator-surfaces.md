---
type: research
status: draft
created: 2026-08-27
source: BUG-011, BUG-012
---

# Research: shared view partials for the configurator surfaces

## Problem
Two duplication clusters in the Blade views:
1. `shop/partials/configurator.blade.php` and
   `components/seller/buyer-view.blade.php` duplicate the option-label
   branch (standalone vs add-on price suffix), the price-breakdown `<dl>`
   markup, and the serialized-unit card markup. BUG-012 edited the same
   branch in both files.
2. Eight seller screens hand-duplicate the
   `grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_380px]` wrapper
   plus the `<x-seller.buyer-view>` slot (hub, six sections, Basics).

## Question
Would a shared partial (e.g. `shop/partials/option-select.blade.php` with a
live/disabled flag) and an `<x-seller.editor-layout>` component remove the
duplication without coupling the shop and seller surfaces too tightly?

## Scope
Research only — inventory the duplicated blocks, propose the partial/component
boundaries, estimate blast radius. No code changes.

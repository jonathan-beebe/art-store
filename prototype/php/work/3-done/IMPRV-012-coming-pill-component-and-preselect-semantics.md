---
id: IMPRV-012
type: improvement
status: resolved
created: 2026-08-27
resolved: 2026-08-27
---

# IMPRV-012: One coming-pill component and honest preselect semantics

## Problem
Two duplications/mismatches left by the DSGN-001 rewrites:
1. The "coming — not in this version" pill markup (rounded-full border
   gray classes) is copy-pasted verbatim 16 times across the six seller
   configurator screens and the editor hub.
2. On the Choices screen each option's "preselected" control is
   `type="radio"`, but every option row is its own form with no shared
   `name` group, so the control never behaves as a native radio; the
   exclusivity is enforced server-side only
   (app/Http/Controllers/Seller/OptionValueController.php).

## Goal
The seller screens carry one source of truth for the coming pill and a
preselect control whose semantics match its behavior.

## Outcome
The pill renders from a single Blade component everywhere it appears, and
the preselect control's markup behaves the way it reads (or its copy says
what saving does), with the existing exclusivity feature test still green.

## Why it matters
Sixteen copies of one pill drift on the first copy edit; a radio that
never radios misleads keyboard and screen-reader users about what a click
will do before Save.

## Related work
- DSGN-001 (source of both)

## Working

### Coming-pill count: 6 copies of the pill markup, not 16

Grepping the six configurator screens plus the editor hub for the pill's
signature class (`rounded-full border border-gray-200 dark:border-gray-700
… px-2 py-0.5 text-xs`) turns up 6 occurrences, not 16:

| File | Line | Text | Drift |
| --- | --- | --- | --- |
| `modifiers/index.blade.php` | 166 | "coming — not in this version" | carried an extra `bg-gray-100 dark:bg-gray-800` no other copy has |
| `modifiers/index.blade.php` | 306 | "not yet" | no `bg-*`; only copy with different wording |
| `variants/index.blade.php` | 233 | "coming — not in this version" | no `bg-*`; real em dash character |
| `variants/units/index.blade.php` | 121 | "coming — not in this version" | no `bg-*`; `&mdash;` HTML entity |
| `description-sections/index.blade.php` | 64 | "coming — not in this version" | no `bg-*`; real em dash character |
| `description-sections/index.blade.php` | 114 | "coming — not in this version" | no `bg-*`; real em dash character |

DSGN-001's own "Working" log names 11 honest-note placements for
deferred/gap stories (B8, B10, A8, C4, C5, C8/D7/D8, C10, D4, D6, D2, C1).
All 11 exist on the seven screens, plus one more (E3, the add-on-checkboxes
note) — 12 total — but only 6 of the 12 (B10, B8, A8, C1, D2, D4) render as
the pill; the other 6 (C4, C5, C8/D7/D8, C10, D6, E3) are plain prose
sentences ending "isn't/aren't … yet" with no pill at all. The ticket's count
of 16 does not match either total found in the codebase. The component
extraction below covers the 6 actual pill copies — the literal ask ("extract
the coming pill … replace all copies") — and leaves the 6 prose-only notes
alone; folding plain sentences into a pill component is a wording/visual
change beyond what this ticket scoped, not a duplication this ticket named.

### Component

`resources/views/components/seller/coming-pill.blade.php` — an anonymous
Blade component (`@props(['text' => 'coming — not in this version'])`),
matching the anonymous style of `components/admin/nothing.blade.php` and
`components/form/field.blade.php`: no state or logic beyond the one default,
so no PHP class earns its keep. All 6 call sites now render
`<x-seller.coming-pill />`; the one wording variant renders
`<x-seller.coming-pill text="not yet" />`. The bg-gray-100 drift on the
modifiers B10 copy is dropped — deliberate, matching the other 5. Rendered
output is otherwise byte-identical (real em dash character throughout;
`&mdash;` and `—` render identically, so standardizing on the literal
character changes no visible output).

Test: `app/View/Components/Seller/ComingPillTest.php` — sidecar-style home
next to `BuyerViewTest.php` (the existing component-test precedent), using
the same `Blade::render()` pattern; no production class sits beside it since
the component is anonymous, so it is not covered by the coverage gate (which
only scans `app/`).

### Preselect control: radio → checkbox with what-saving-does copy

Decision: `type="checkbox"`, label reads "preselected — saving clears any
other preselected option". Why: each option row posts its own `<form>` to
its own route; a native radio group requires every member to share the same
form owner, so giving each row's input the same `name` without a shared
form produces the input-type equivalent of theater — several rows can show
checked at once, and a click never clears any sibling in the browser, only
the label read out to assistive tech promises otherwise. Restructuring the
options into one shared form (with per-button `formaction` overrides to
keep each row's independent submit route) would make the radio honest, but
is a materially bigger change for the same screen; the checkbox is the
simplest fix that makes the markup say only what it does — "checking this
and saving will do X" — with the actual exclusivity still enforced
server-side in `OptionValueController::unsetOtherDefaults()`, unchanged.
The POST contract is unchanged too (`name="is_default"`, `value="1"`, sent
only when checked), so `OptionValueRequest` needed no change.

Test: `OptionAxisControllerTest` gained `'IMPRV-012: renders the preselect
control as a checkbox naming what saving does, not a radio with no group'`,
pinning `type="checkbox" name="is_default"`, the absence of
`type="radio" name="is_default"`, and the new copy. The existing exclusivity
test, `OptionValueControllerTest`'s `'unsets the previous default when
saving another option as preselected'`, posts the field directly and needed
no change — it stayed green throughout.

### Other test changes

- `UnitControllerTest`: added `'C1: shows the honest note that per-piece
  photos are not in this version'` — the units screen's pill previously had
  no feature-level assertion at all.
- `DescriptionSectionControllerTest`: added a `coming — not in this version`
  assertion to the existing `'D4: shows the honest note about per-listing
  sections'` test (D2's test already asserted the pill text).
- `ModifierControllerTest`'s `'B8+B10+E3: …'` and `VariantControllerTest`'s
  `'A8: …'` already asserted both screens' pill text; unchanged.

### Result

Files changed: `resources/views/components/seller/coming-pill.blade.php`
(new); `resources/views/seller/listings/{modifiers,variants,variants/units,
description-sections,option-axes}/index.blade.php`;
`app/View/Components/Seller/ComingPillTest.php` (new);
`app/Http/Controllers/Seller/{OptionAxisControllerTest,UnitControllerTest,
DescriptionSectionControllerTest}.php`.

Full suite: 2729 passed, 7785 assertions; `make check` (lint → assets →
coverage) green at 100% line coverage throughout.

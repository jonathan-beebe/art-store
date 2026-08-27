---
id: IMPRV-012
type: improvement
status: open
created: 2026-08-27
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

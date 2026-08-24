---
id: IMPRV-012
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-012: Field-level errors on every form

## Problem
Only the listing form renders errors beside the field with `aria-describedby`; the FAQ form, the ship form, the checkout form, the pay form, and the message forms flash a single sentence or list errors above the form. PHP renders every form through one `<x-form.field>` component with a single global refusal mapping (`DomainRuleViolation → back()->withErrors`).

## Goal
Every form in every site tells the user which field is wrong, next to it, the same way.

## Outcome
Every POST form re-renders with the submitted values kept and each error beside its field, linked with `aria-describedby`; a refused domain rule with no field renders as a form-level error in one shared place; one template partial/helper carries the markup; tests cover one field error and one form-level error per form.

## Why it matters
The alignment brief names "form sanitization and errors" as a shared CX; PHP meets it and Node does not.

## Discovery notes
RFCTR-003's result-union parsers already return per-field errors; the gap is rendering. An EJS partial taking `{name, label, value, error}` matches the PHP component's role.

## Related work
- RFCTR-003
- docs/alignment.md §7

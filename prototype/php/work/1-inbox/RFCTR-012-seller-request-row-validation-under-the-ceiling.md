---
id: RFCTR-012
type: refactor
status: open
created: 2026-08-31
---

# RFCTR-012: seller request row validation under the ceiling

## Problem

The cognitive-complexity gate (function ≤ 8; commit 0c8700b) holds three
baseline entries in the seller form requests, all conditional-rule
builders over row data:

- `ListingRequest::validateRows()` at 16
  (`app/Http/Requests/Seller/ListingRequest.php:302`)
- `DescriptionSectionRequest::completeRows()` at 9
  (`app/Http/Requests/Seller/DescriptionSectionRequest.php:116`)
- `OptionValueRequest::rules()` at 9
  (`app/Http/Requests/Seller/OptionValueRequest.php:30`)

## Goal

Row validation in the seller requests reads under the same ceiling as the
rest of the codebase.

## Outcome

The three entries are deleted from
`prototype/php/src/phpstan-baseline.neon` and `make analyse` passes; every
existing sidecar test passes unmodified, including the validation-negative
cases; no new baseline entries appear.

## Why it matters

These requests guard what sellers can publish; their branching is where
validation gaps slip in, and two of the three sit one point over the
ceiling — small extractions keep them from growing past it while the
gate's baseline shrinks toward empty.

## Discovery notes

Advisory.

- The two 9s are borderline — likely one extracted predicate or rule
  method each. `validateRows()` at 16 is the real work; its per-row
  conditional cascade may want the rows normalized first (the
  `DescriptionSectionRows` support object from commit 010f363 shows the
  house pattern for row shaping) so validation reads flat.
- Laravel-idiomatic escape hatches — `Rule::forEach()`, custom rule
  objects, `withValidator()` — tend to lower the score without moving
  validation out of the request, which is where the house style wants it.

## Related work

- Commit 0c8700b — the gate and baseline this ticket shrinks
- Commit 010f363 — `DescriptionSectionRows`, the row-shaping precedent

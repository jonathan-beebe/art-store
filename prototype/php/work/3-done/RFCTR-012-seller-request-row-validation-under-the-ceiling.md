---
id: RFCTR-012
type: refactor
status: resolved
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

## Working

Existing sidecars already pinned every branch touched (both-blank, missing
label, missing price, invalid price, and complete rows for the versions and
extras row sets; is_array/half-filled/complete rows for the description
section kinds; blank/invalid/valid surcharge and standalone/add-on price
paths for option values) — confirmed 139 passing before touching any
production code, so no characterization tests were added.

`OptionValueRequest::rules()`: the two inline rule closures (surcharge,
price) moved to `surchargeRule()`/`priceRule()`, private methods returning
`Closure` — the same shape `ListingRequest::absolutePrice()` already used.
Score 9 → 0 (the two extracted methods score 5 and 4).

`DescriptionSectionRequest::completeRows()`: the inner per-row `foreach`
building the label/value/etc. map moved to `rowValues()`. Score 9 → 5 (new
method scores 3).

`ListingRequest::validateRows()`: the per-row label/price extraction moved
to `rowLabelAndPrice()`/`filledField()`, and the per-row error-flagging plus
completeness check moved to `flagIncompleteRow()`. Score 16 → 6 (new methods
score 0, 3, 4).

Hand-simulated the vendored `tomasvotruba/cognitive-complexity` analyzer
(`NestingNodeVisitor` + `ComplexityAffectingNodeFinder`) against each method
before touching code — every predicted score matched what `make analyse`
reported once the baseline entries were deleted. Nothing resisted the
ceiling; each closure/loop extraction dropped the enclosing method well
under 8 in one pass.

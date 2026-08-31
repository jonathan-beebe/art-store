---
id: RFCTR-013
type: refactor
status: resolved
created: 2026-08-31
---

# RFCTR-013: the complexity baseline's last two entries

## Problem

The cognitive-complexity gate (function ≤ 8; commit 0c8700b) holds two
baseline entries outside the configurator and seller-request clusters:

- `LogRowQuery::conditions()` at 10
  (`app/Logging/Admin/LogRowQuery.php:216`) — the log viewer's
  filter-to-SQL translation
- `AddToCart::__invoke()` at 9 (`app/Actions/Cart/AddToCart.php:34`) —
  the add-to-cart action's guard-and-merge branching

## Goal

The baseline is empty and the gate stands on its own.

## Outcome

Both entries are deleted from `prototype/php/src/phpstan-baseline.neon` —
leaving it holding nothing once RFCTR-011 and RFCTR-012 land — and
`make analyse` passes; every existing sidecar test passes unmodified; no
new baseline entries appear.

## Why it matters

An empty baseline turns the complexity gate from "new code only" into a
property of the whole codebase; these two are the cheapest entries to
clear and the difference between a shrinking exception list and a
finished one.

## Discovery notes

Advisory.

- Both are one point or two over — one extraction each is the likely
  shape: a filter's condition builder split per filter kind in
  `conditions()`, a guard or merge step named out of `__invoke()`.
- No ordering dependency on the other two tickets; this can land first or
  last. Whichever ticket lands last deletes a then-empty
  `phpstan-baseline.neon` include only if the file is removed too —
  otherwise an empty parameters list is fine; match what phpstan accepts.

## Related work

- Commit 0c8700b — the gate and baseline this ticket shrinks
- RFCTR-011, RFCTR-012 — the other baseline clusters

## Working

- Verified both branches against the vendored `tomasvotruba/cognitive-complexity`
  scoring (`ComplexityNodeVisitor` + `NestingNodeVisitor`): `&&` and ternaries
  each add 1 operation point; a node only earns a nesting bonus when it is the
  first incrementing node encountered strictly deeper than the previous one.
  Hand-traced both functions against this model and got 10 and 9 exactly,
  matching the baseline messages — confirms the extraction shapes below clear
  the gate rather than just probably clearing it.
- `LogRowQuery::conditions()` (10 → 7): extracted the `foreach`
  column-equality loop (the `if ($value !== null)` nested inside it was
  worth 1 op + 1 nesting, the `foreach` itself 1 op) into a new private
  `columnEqualityConditions()`. `conditions()` is now seven flat `if`s, no
  nesting. New method's own complexity: 3.
- `AddToCart::__invoke()` (9 → 6): extracted the `$listingHasVariants ? ... : ...`
  ternary (plus its two `&&` availability/serialized checks — nested inside
  the `use()` closure, so that ternary was carrying the function's one
  nesting point) into a new private `resolveQuantity()`. New method's own
  complexity: 3.
- Every branch moved was already pinned: `AddToCartTest.php` covers the
  no-variant stock path directly; the `listingHasVariants: true` /
  `ConfiguredCartQuantity::withinStock` path is exercised across
  `PlaceOrderTest`, `OrderTest`, `DeclineFulfillmentTest`, and
  `FinalizeOrderTest`. `LogRowQueryTest.php` covers every filter branch in
  `conditions()`, mirrored-column loop included. No new behavior, so no new
  tests — pure extract-method, characterization already green pre-refactor.
- `phpstan-baseline.neon` is now `parameters: { ignoreErrors: [] }`.
  `make analyse` accepts an empty `ignoreErrors` list without complaint, so
  kept the file and its include in `phpstan.neon` rather than removing it —
  matches the ticket's "if an empty baseline include is accepted, keep the
  empty file."
- Full gate green: `make lint` (Pint + PHPStan, empty baseline), `make
  coverage` (100% lines, full suite passing).

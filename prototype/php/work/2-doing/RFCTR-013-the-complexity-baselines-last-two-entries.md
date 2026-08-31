---
id: RFCTR-013
type: refactor
status: open
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

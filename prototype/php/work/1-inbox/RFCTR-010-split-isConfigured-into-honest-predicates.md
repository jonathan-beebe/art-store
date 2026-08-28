---
id: RFCTR-010
type: refactor
status: open
created: 2026-08-28
---

# RFCTR-010: Split `isConfigured()` into honest predicates

## Problem
`CartItem::isConfigured()` and the matching check on `OrderItem` answer
"resolves to a variant" while call sites use them to mean "has a priced
breakdown to total." BUG-007 came from exactly this conflation: pricing was
gated on the variant question at three call sites (`CartItem::toLine()`,
`PlaceOrder::snapshotItems()`, `OrderItem::lineTotal()`) when the breakdown
question was the one that mattered. BUG-007's fix corrected the call sites;
the conflated name remains for the next caller to trip on.

## Goal
Each predicate's name states the domain question it answers, and no call
site can pick the wrong one without it reading wrong.

## Outcome
- [ ] Every `isConfigured()` call site on `CartItem`/`OrderItem` is
      classified by the question it actually asks, and the method is split
      or renamed accordingly (e.g. `hasVariant()` for "resolves to a
      variant", `hasPricedBreakdown()` for "has a frozen/current breakdown
      to total" — final names follow the naming skill).
- [ ] The conflated name `isConfigured()` is gone from `CartItem` and
      `OrderItem`.
- [ ] Behavior is unchanged: characterization tests protect the existing
      behavior before the rename, and the full suite stays green.
- [ ] `make check` green; coverage 100%; journal updated.

## Why it matters
Human-directed 2026-08-28: when a bug exists because a term is conflated,
rename each to describe its domain and remove the conflation — the source
of the bug, not only its instance.

## Related work
- BUG-007 (prototype/php/work/3-done/) — the bug the conflation produced

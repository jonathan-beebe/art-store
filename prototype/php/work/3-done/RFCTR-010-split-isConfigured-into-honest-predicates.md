---
id: RFCTR-010
type: refactor
status: resolved
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
- [x] Every `isConfigured()` call site on `CartItem`/`OrderItem` is
      classified by the question it actually asks, and the method is split
      or renamed accordingly (e.g. `hasVariant()` for "resolves to a
      variant", `hasPricedBreakdown()` for "has a frozen/current breakdown
      to total" — final names follow the naming skill).
- [x] The conflated name `isConfigured()` is gone from `CartItem` and
      `OrderItem`.
- [x] Behavior is unchanged: characterization tests protect the existing
      behavior before the rename, and the full suite stays green.
- [x] `make check` green; coverage 100%; journal updated.

## Working

### Call-site classification

Every existing `isConfigured()` call site asks the same question —
"does this line resolve to a variant" — none asks the breakdown question.
BUG-007's fix had already moved the three breakdown-question call sites
(`CartItem::toLine()`, `PlaceOrder::snapshotItems()`,
`OrderItem::lineTotal()`) off the predicate before this ticket started.

| Call site | Question asked | New name |
| --- | --- | --- |
| `CartItem::currentBreakdown()` | Load the variant to price against, or price bare? | `hasVariant()` |
| `CartItem::currentAvailability()` | Report default selectable, or read the variant's own availability? | `hasVariant()` |
| `OrderItem::lineTotal()` (pre-existing inline check, not `isConfigured()`) | Total the frozen breakdown, or `unit_price_cents * quantity`? | extracted to `hasPricedBreakdown()` |
| `StockMovement::claim()` / `release()` | Move listing stock directly, or drill into variant/unit? | `hasVariant()` |
| `PlaceableLineBuilder::for()` | Build a bare `PlaceableLine`, or one carrying variant/unit fields? | `hasVariant()` |
| `PlaceOrder::configurationSnapshot()` | Snapshot `null`, or the axis-pair configuration? | `hasVariant()` |
| `shop/cart.blade.php` | Show axis/answer detail and availability, or nothing? | `hasVariant()` |
| `seller/orders/show.blade.php`, `admin/orders/show.blade.php` | Render `<x-order-item-detail>`, or skip it? | `hasVariant()` |
| `components/order-item-detail.blade.php` | Render configuration pairs and breakdown lines, or nothing? | `hasVariant()` |
| `CartItemTest`, `OrderItemTest`, `SmokeTest` assertions | Pin variant-resolution state in test setup | `hasVariant()` |

### Names chosen

- `hasVariant(): bool` on both `CartItem` and `OrderItem` — replaces
  `isConfigured()` verbatim (`variant_id !== null`). Both models keep the
  same method name so the `CartItem|OrderItem` union call sites
  (`StockMovement`, `PlaceableLineBuilder`) stay polymorphic.
- `hasPricedBreakdown(): bool` on `OrderItem` — extracted from the inline
  `$this->price_breakdown_json === null` check in `lineTotal()`. It was a
  single call site, but leaving the second domain question anonymous and
  inline is the shape that let `isConfigured()` get reached for by mistake
  in the first place (BUG-007); naming it makes the correct predicate
  discoverable to the next caller. `CartItem` has no equivalent — its
  breakdown is always computed live, never absent.

### Test changes

No new characterization tests were needed: `CartItemTest`, `OrderItemTest`,
`PlaceableLineBuilderTest`, `StockMovementTest`, and `SmokeTest` already
exercised every call site's both branches (with and without a variant, with
and without a frozen breakdown) before this refactor, confirmed by running
the baseline suite (2732 passed) before touching any production code. All
`->isConfigured()` assertions in `CartItemTest`, `OrderItemTest`, and
`SmokeTest` were mechanically renamed to `->hasVariant()`. The full suite
stayed at 2732 passed after the rename/split, and `make check` reports
100% coverage.

## Why it matters
Human-directed 2026-08-28: when a bug exists because a term is conflated,
rename each to describe its domain and remove the conflation — the source
of the bug, not only its instance.

## Related work
- BUG-007 (prototype/php/work/3-done/) — the bug the conflation produced

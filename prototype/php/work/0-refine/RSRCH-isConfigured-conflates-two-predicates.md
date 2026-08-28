---
type: research
status: draft
created: 2026-08-27
source: BUG-007
---

# Research: `isConfigured()` conflates two predicates

## Problem
`CartItem::isConfigured()` and the matching check on `OrderItem` answer
"resolves to a variant" while call sites use them to mean "has a priced
breakdown to total." BUG-007 happened because pricing was gated on the
variant question at three call sites (`CartItem::toLine()`,
`PlaceOrder::snapshotItems()`, `OrderItem::lineTotal()`) when the breakdown
question was the one that mattered.

## Question
Would splitting the two predicates (e.g. `hasVariant()` vs
`hasPricedBreakdown()`) across `CartItem`/`OrderItem` remove this bug
category? Survey the remaining `isConfigured()` call sites and classify which
question each one actually asks.

## Scope
Research only — classify call sites, propose the predicate split and rename,
estimate blast radius. No code changes.

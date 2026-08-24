---
id: IMPRV-004
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-004: Checkout refusal lists every blocked line and the cart gates checkout

## Problem
`PlaceOrder` stops at the first `DomainRuleViolation` and the controller redirects to `/cart` with one message, so a customer with two stale lines fixes one and is refused again; the cart page keeps the checkout button live on a line that is sold out or off sale. Node's `planOrderPlacement` returns every blocked line with a reason (`removed | off_sale | sold_out | short_stock`) and the cart page disables checkout while any line is blocked.

## Goal
A customer learns everything wrong with their cart at once, before and at checkout.

## Outcome
Checkout re-renders (422) with every blocked line and its reason; the cart page marks each blocked line and disables the checkout control while any exists; the pay page refuses an order that went stale between placement and payment with the same shape; tests cover each reason and the multi-line case.

## Why it matters
"Fix one, get refused again" is the CX the alignment brief rules out; Node has the finished shape.

## Discovery notes
A value object (`OrderPlacementPlan`) that folds the cart into `placeable | blocked(lines)` keeps the decision testable without the database, in the PHP prototype's value-object idiom.

## Related work
- BUG-001 (first-violation redirect)
- prototype/node BUG-003 (planOrderPlacement)

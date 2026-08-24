---
id: BUG-004
type: bug
status: open
created: 2026-08-23
---

# BUG-004: Checkout 500s on a stale cart line instead of listing what is wrong

## Problem
`Listing#take_stock!` raises `ArgumentError` when a cart line exceeds stock or the listing has left the storefront, and neither `Order.place` nor `Shop::CheckoutsController` rescues it, so a customer whose cart went stale gets a 500. The cart page keeps the checkout button live on a sold-out or off-sale line. Node's `planOrderPlacement` returns every blocked line with a reason (`removed | off_sale | sold_out | short_stock`) and re-renders the list with 422; PHP `BUG-001` and Node `BUG-003` fixed the same fault.

## Goal
A customer learns everything wrong with their cart at once, before and at checkout, and never sees a 500 for it.

## Outcome
Checkout re-renders (422) with every blocked line and its reason; the cart page marks each blocked line and disables the checkout control while any exists; the pay page refuses an order that went stale between placement and payment with the same shape; tests cover each reason, the multi-line case, and the former 500.

## Why it matters
A 500 on checkout is a lost sale and the one bug all three prototypes were supposed to have fixed.

## Discovery notes
A PORO `OrderPlacement` (or a class method on `Order`) that folds the cart into placeable-or-blocked-lines keeps the decision testable; `take_stock!` then only runs on a placeable plan inside the transaction.

## Related work
- prototype/node BUG-003, prototype/php BUG-001
- FEAT-005 (storefront)

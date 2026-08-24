---
id: IMPRV-005
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-005: Merge folds the cart and de-duplicates favorites

## Problem
`MergeAnonymousCustomer` re-points `carts.customer_id`, so a verified customer who shopped anonymously ends up with two carts and a `currentCart` heuristic picks one; favorites are re-pointed with `update_all` and rely on the unique index to drop duplicates. Node's `planCustomerMerge` folds cart quantities (sum, clamp to stock, drop zero lines) and de-dupes favorites as a pure plan with its own tests.

## Goal
Merging an anonymous customer into a verified one leaves exactly one cart and one favorites set, with nothing lost.

## Outcome
After a merge the owner has one cart whose lines are the sum of both clamped to stock, favorites are the union, conversations are folded (already), blocks and sent messages are re-pointed (already), and a test asserts every `customer_id` column is either in `CustomerOwnedTables::all()` or in an explicit left-behind list; the `currentCart` heuristic is gone; `docs/identity.md` states the fold.

## Why it matters
Retro item 4 asked for the merge as a fold; PHP has the manifest and the conversation fold but not the cart.

## Discovery notes
A `CustomerMergePlan` value object over both customers' cart lines and favorites, in the prototype's value-object idiom, applied inside the existing transaction.

## Related work
- FEAT-002 (identity), RFCTR-004
- prototype/node RFCTR-004 (planCustomerMerge)

---
id: IMPRV-003
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-003: Merge folds the cart and de-duplicates favorites, and three actors share one browser

## Problem
`Customer#merge_from` (or the equivalent) re-points `carts.customer_id`, so a verified customer who shopped anonymously ends up with two carts; favorites rely on the unique index during `update_all`. Every sign-in calls `reset_session`, so signing in on the seller site drops the customer and admin sessions — a reviewer cannot demo seller + customer + admin side by side in one browser, which Node and PHP allow. The Node merge (`planCustomerMerge`) folds cart quantities (sum, clamp to stock, drop zero lines) and de-dupes favorites as a pure plan.

## Goal
Merging leaves exactly one cart and one favorites set with nothing lost, and one browser can hold all three actors at once.

## Outcome
After a merge the owner has one cart whose lines are the sum of both clamped to stock, favorites are the union, conversations are folded (already), sent messages are re-pointed (already), blocks are re-pointed, and a test asserts every `customer_id` column is either in `MERGED_ASSOCIATIONS` or in an explicit left-behind list; signing in as any actor keeps the other two signed in (one session key per actor, rotated on that actor's sign-in only) and the smoke walk signs in all three in one session; `docs/identity.md` states both.

## Why it matters
Retro item 4 asked for the merge as a fold; the three-actors demo is how the prototypes get compared side by side.

## Discovery notes
A `CustomerMerge` PORO (or a class method) over both customers' cart lines and favorites, applied inside the existing transaction; per-actor session keys with `session.delete(:customer_id)`-style sign-out instead of `reset_session`, keeping CSRF and session-fixation protection by rotating the session id via `request.session_options[:renew] = true` on sign-in.

## Related work
- FEAT-002 (identity), BUG-001 (merge threads)
- prototype/node RFCTR-004 (planCustomerMerge)

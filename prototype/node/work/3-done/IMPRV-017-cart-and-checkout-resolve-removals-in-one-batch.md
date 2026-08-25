---
id: IMPRV-017
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-017: Cart and checkout resolve listing removals in one batch

## Problem
`cartContents` (`app/actions/carts/cart-contents.ts:67-85`) loops over cart rows awaiting `activeListingRemoval` per line, and each call selects the listing's entire removal history (`app/actions/moderation/active-listing-removal.ts:24-30`). `placeOrder` (`app/actions/orders/place-order.ts`) then repeats the same per-line queries a second time (`withRemovals`) — and both rounds run inside the `BEGIN IMMEDIATE` checkout transaction, so 2N sequential queries extend the write-lock hold time on the app's most contended write path.

The batched idiom already exists in this codebase: `activeRemovalsByListing` (`sites/admin/queries/listing-rows.ts:95-114`) reads all removals for a set of listings in one `where listingId in (...)` query into a Map.

## Goal
Checkout's write-lock hold time stops scaling with per-line removal queries.

## Outcome
Cart view and checkout each issue one removal lookup for the whole cart, checkout does not re-ask what `cartContents` already answered, and the unavailable/removed rules produce exactly the same outcomes as today.

## Why it matters
Checkout is a `BEGIN IMMEDIATE` transaction on a single-writer database; every statement inside it blocks all other writes. The pure judgment (`unavailableReason`) is fine — the waste is in how the shell fetches its inputs, and the fix matches an idiom the repo already uses, so clarity improves rather than trades away.

## Discovery notes
- One `listingRemovals` read filtered `listingId in (...)` and `liftedAt is null` into a Set/Map serves both callers; the existing `(listing_id, lifted_at)` index fits it.
- `placeOrder` can consume the answer `cartContents` already produced instead of re-querying.

## Related work
- BUG-003 (checkout vs removed listings — the correctness half of this path)

## Working
- 2026-08-25 re-validated: `cart-contents.ts:67-85` still awaits `activeListingRemoval` per line; `place-order.ts` `withRemovals` (111-123) repeats it inside the `BEGIN IMMEDIATE` transaction.
- Plan: batched lookup lives beside `activeListingRemoval` in `app/actions/moderation/active-listing-removal.ts` — one `listingRemovals` read (`listingId in (...)`, `liftedAt is null`) into a Set of listing ids. `cartContents` calls it once and stamps `hasActiveRemoval` onto `CartLineView`; `placeOrder` feeds `contents.lines` straight to `planOrderPlacement` and drops `withRemovals`/`PlacementLine`.
- `CartLineView` then satisfies `PlaceableLine` directly; `unavailableReason` stays untouched.
- Behavior pinned by existing tests: cart-contents.test.ts (removed line stays, marked unavailable, dropped from total) and place-order.test.ts (refuses removed listing, writes nothing).
- Done: `listingsUnderActiveRemoval` added beside `activeListingRemoval` (one `listingId in (...)` + `liftedAt is null` read into a Set; empty input short-circuits). `cartContents` calls it once and stamps `hasActiveRemoval` on each `CartLineView`; the per-line await loop became a plain map. `placeOrder` feeds `contents.lines` to `planOrderPlacement` directly — `withRemovals` and `PlacementLine` deleted, so the checkout transaction issues zero removal queries beyond the one inside `cartContents`.
- Tests: 4 new (3 on the batched function, 1 pinning `hasActiveRemoval` on cart lines), written first and failing for the right reason before the change. Suite 1942/1942 green; coverage 99.43/95.90/99.50 (baseline 99.43/95.89/99.50). `make check` green.
- Validation review: batched SQL semantics equal the old `activeRemoval` existence check for no-removals / only-lifted / mixed cases; `unavailableReason` untouched; no casts or `any`; no refactors recommended.

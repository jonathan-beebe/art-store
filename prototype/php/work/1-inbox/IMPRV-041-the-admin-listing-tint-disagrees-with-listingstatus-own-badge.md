---
id: IMPRV-041
type: improvement
status: open
created: 2026-09-04
---

# IMPRV-041: The admin listing tint disagrees with `ListingStatus`'s own badge

## Problem
`resources/views/admin/listings/show.blade.php` hand-rolls its own tint
`match` (active removal → red, `ForSale` → green, `Draft` → yellow, default
→ gray) instead of reading `ListingStatus::sellerBadgeTint()`, and disagrees
with it: the shared method reads `Draft` as gray, not yellow, and `Sold` as
red, where the admin's copy never names that case and falls through to
gray. `ListingStatus::sellerBadgeTint()`/`sellerBadgeLabel()` are also still
named for the seller portal alone, the same drift IMPRV-034 found and fixed
on `FulfillmentStatus`.

## Goal
One tint-and-label lookup for a listing's status, read by both portals,
named for neither.

## Outcome
`ListingStatus::sellerBadgeTint()`/`sellerBadgeLabel()` renamed to
`badgeTint()`/`badgeLabel()`; `resources/views/admin/listings/show.blade.php`
reads the admin's status pill through them, replacing its own `match`, so a
sold or draft listing's tint in the admin matches what the seller portal
already shows.

## Why it matters
A second, disagreeing copy of a status-to-tint rule is what IMPRV-034
warned the next admin page away from; this one had already been written.

## Discovery notes
- `app/Domain/Listings/ListingStatus.php:63,74` are the methods.
- `resources/views/admin/listings/show.blade.php:3-9` is the disagreeing copy.
- `app/Seller/ListingTable.php`,
  `resources/views/components/seller/listing-status-badge.blade.php` are the
  seller-side callers to update alongside the rename.

## Related work
- IMPRV-034 (the same rename, on `FulfillmentStatus`)

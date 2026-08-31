---
id: FEAT-037
type: feature
status: open
created: 2026-08-31
---

# FEAT-037: archived listings stay visitable and legible in the logs

## Problem

An archived listing answers 404: `ListingController`
(`app/Http/Controllers/Shop/ListingController.php:21`) aborts unless
`isOnStorefront()`, and `ListingStatus::isOnStorefront()`
(`app/Domain/Listings/ListingStatus.php:47-50`) admits only `ForSale` and
`Sold`. The comment there reads "a draft or archived one was never public",
but a listing archived from `ForSale` was public — links a visitor already
holds (bookmarks, a favorites list; commit bce7afe pinned that favoriting an
archived listing succeeds) dead-end, while a `Sold` listing keeps its page
for exactly that reason. Nothing in the logs identifies interest in an
archived piece: `listing.view` fires only on pages that render.

## Goal

An archived piece keeps a public page — out of browse and search, visible at
its link — and its views read distinctly in the logs.

## Outcome

Browse, search, and the home page continue to exclude archived listings
(true today via the storefront-status scopes). Visiting an archived
listing's existing URL renders a page presenting the piece as archived, with
no purchase path. An operator can count archived-listing views in the log
viewer as their own population. Draft listings and listings under an active
removal answer 404 as today; `Sold` pages are unchanged.

## Why it matters

Views and favorites of archived work are a signal to the seller that a piece
is worth relisting — the reporter wants that signal captured rather than
lost to a 404. Beta testers will reach archived pieces through favorites
lists and shared links; every such visit today is a dead end the logs record
only as a 404.

## Discovery notes

Advisory.

- Reporter's suggestion: give archived pieces an `/archived/...` route and
  redirect the canonical listing URL there, so archived views separate in
  the logs by path alone — the log viewer's domain buckets already read
  `data.path`. One way among several; the `listing.view` story also already
  carries `data.status`, which may be enough legibility without a new route.
- alignment.md §1 fixes storefront listing pages at `/art/:slug`; check
  whether a new public route shape is contract-relevant before inventing
  one, since the other two prototypes must match contract-fixed shapes.
- A removal outranks status (`ListingAvailability::isOnStorefront`) — an
  archived listing under an active removal must stay hidden whatever the
  archived page does.
- `Archived` is terminal in the state machine
  (`ListingStatus.php:27`, `Archived => []`) — "relist" is not a seller
  action today. This ticket only makes the signal visible; if acting on it
  needs an `Archived → ForSale` transition, scope that separately (it may
  touch the shared lifecycle contract).
- Worth checking while in there: how the favorites list renders an archived
  listing now that its link can lead somewhere.

## Related work

- Commit bce7afe — pinned that favoriting an archived listing succeeds
- Commits 5c18926..769f36f — log legibility work this signal would ride on

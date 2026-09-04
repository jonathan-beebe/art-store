---
id: FEAT-062
type: feature
status: open
created: 2026-09-04
---

# FEAT-062: A store has an admin analytics page, reached from its events and from the seller

## Problem
The admin activity feed names a `store` analytics subject as an unlinked chip (FEAT-058 left the page out), and the admin's seller pages do not mention the seller's store. A store and its seller are near-synonyms to the admin, and the admin cannot get from a `store.view` event to the store or from the seller to the store.

## Goal
An admin can open any store from wherever the admin sees it named, and sees its views the way they see a listing's.

## Outcome
- `GET /admin/analytics/stores/{store}?range=&event=` renders identity (name, slug, seller with a link, published state), the tiles, the daily strip, and the feed the listing page renders, scoped to `store.view` and any later store events; unknown ids and prefixes answer 404; unknown query values 400.
- Every feed row naming a store links to it; the admin seller pages (list row and detail) show the seller's store name with a link when a profile exists and its visibility; the store page links back to the seller.
- `App\Analytics\Admin\EntityActivity` gains the store branch its listing branch has; the admin nav and breadcrumbs treat the page like the listing page.
- `make precommit` green; `make check` green before the PR.

## Why it matters
A seller has a store, so the admin's picture of a seller is incomplete without it, and an event that names something the admin cannot open is a dead end.

## Discovery notes
- `__local__/design/seller-portal/DECISIONS.md` decision 12. Reuse the admin listing analytics page (`Admin\Analytics\ListingController`, its view, `EntityActivity::forListing`) as the template.

## Related work
- FEAT-058, FEAT-044..048

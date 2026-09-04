---
id: FEAT-062
type: feature
status: resolved
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

## Working
`EntityActivity::forStore()` mirrors `forListing()`: identity facts
(slug, seller name, visibility), tiles (views, distinct actors, busiest
day), daily strip, feed (other party the visiting actor). `totalForName`/
`distinctActorsForListing` generalized to `totalForSubject`/
`distinctActorsForSubject` so the listing and store branches share one
query each. `Admin\Analytics\StoreController` reuses
`AnalyticsEntityQueryRequest` and the `entities/show.blade.php` template
unchanged (nav highlighting already matches on `admin.analytics.*`, so no
separate breadcrumb work was needed). Every feed row naming a store now
links to it (`feedRowForStore` looks the store up and links when it still
exists); the visits panel's condition changed from `kind !== 'listing'` to
an explicit verified/anonymous check so the new `store` kind doesn't
render an empty visits panel either.

Admin seller list row (`sellers-cells.blade.php`) and detail
(`sellers/show.blade.php`) both show the store name and a
`x-admin.status-badge` visibility pill. First pass linked the name inside
the list row — invalid HTML, since `x-pane-row` is itself an `<a>` and an
anchor can't nest inside one. Fixed: the list row shows the store as plain
text (the row's own link already goes to the seller, where the store's
real link lives in the detail `<dl>`); only the detail page's store name
is an actual link. `SellerController` eager-loads `storeProfile`
(index query and the show route's `loadMissing`); its query-count test
moved 12 → 14 sqlite queries for the load plus the one-row lookup.

`docker compose run --rm app php vendor/bin/pest` green for every touched
sidecar (StoreControllerTest, ActorControllerTest, ListingControllerTest,
SellerControllerTest) and `pint --test` clean. `make check` intentionally
skipped — the orchestrator runs one gate on the merged branch; `make
precommit` runs on commit via the hook.

### Review pass

`Admin\Analytics\StoreController` and `ListingController` shared their
range- and event-link builders almost verbatim; both now call
`App\Analytics\Admin\EntityPageLinks::range()`/`event()` (`ActorController`'s
own entity-page links moved onto the same class for the same reason,
leaving its own all-actors-index links, a different shape, untouched).
The event-name filter each page offers narrowed to
`AnalyticsEventName::forSubject('listing'|'store')` rather than every
case — a listing's or a store's feed can never carry an order or a
checkout event, so the filter never offered one that could ever match.
`EntityActivity`'s listing and store `whereIn` reads for their feed's
other-party models were inconsistently guarded against an empty id list;
both now go through one `modelsById()` helper that skips the query when
there is nothing to look up. The two "list row and detail page" seller
tests actually only proved the detail page (the list row carries no link
to assert on) — split into one test per page, the list-row one asserting
the link's absence there explicitly.

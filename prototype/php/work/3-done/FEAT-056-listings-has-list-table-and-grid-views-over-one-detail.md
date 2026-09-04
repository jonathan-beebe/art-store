---
id: FEAT-056
type: feature
status: resolved
created: 2026-09-03
---

# FEAT-056: Listings has list, table, and grid views over one detail

## Problem
The seller listings tool is one shape: a 384px list pane and a detail pane (`resources/views/seller/listings/index.blade.php`, `show.blade.php`, `x-seller.list-detail`). A seller with thirty pieces cannot compare them on price, stock, views, or sales, cannot sort them, and never sees their inventory the way a buyer sees the shop.

## Goal
A seller can look at their inventory three ways — a list beside a detail, a sortable table of every number, and the storefront's own grid — and open any piece from any of them.

## Outcome
- One header above the tool: "Listings" with the count, a three-way view switch (List, Table, Grid) with icons, a sort select on Table and Grid, and New listing.
- List is today's list/detail, unchanged in behavior.
- Table is condensed: cover thumb and title with medium and dimensions, status, price, stock, views, favorites, cart adds (the range-bound analytics counts), sold and revenue (paid, live fulfillments, all time), conversion, updated. Every column header sorts by link with `aria-sort`; the current column flips direction.
- Grid is the storefront product list: square cover, title and price, medium and stock, a stats line (views, favorites, sold), the status badge over the image.
- Table and grid rows open the listing's detail as an overlay at `2xl` and up, and as the full content area with a back link below that. The list pane opens it beside the list as today. One detail component renders in all three places: today's show content plus dimensions, sold, revenue, last sold, and the range-bound view strip.
- View, sort, direction, and range are query parameters; unknown values answer 400. The New listing dialog keeps working from every view. `make precommit` green; `make check` green before the PR.

## Why it matters
The brief: "This is their inventory. It should be something they are proud of." A table answers "which of my pieces earn" in one glance; a grid shows the shop as buyers see it; the list stays for working one piece at a time. One detail component keeps the three from drifting.

## Discovery notes
- Domain `ListingView` and `ListingSort` (column enum, direction, `default()` per view) under `App\Domain\Seller`; a FormRequest owns the 400.
- Adapter `App\Seller\ListingTable`: the seller's listings with sold/revenue from `order_items` joined to live fulfillments (app connection), plus per-listing analytics counts for the range (analytics connection, one `whereIn`, joined by id in PHP the way `App\Analytics\Admin` classes do). Sort in PHP over the joined rows.
- Overlay: a native `<dialog open>` rendered by the show view when `from=table|grid` and the viewport is `2xl`, with the takeover markup for smaller viewports — Tailwind's `2xl:` variants can hide one and show the other from the same response, so no JavaScript decides.
- Extract today's detail into `x-seller.listing-detail`; the existing `Listing::imageUrl()` and `ListingStockLabel` serve every view.
- View switch and sort select follow the seller chrome's control classes (see the canvas `.seg` and `.field` styles, lifted from the app).

## Related work
- FEAT-025..029 (item configurator) — the focused editor stays the edit surface
- IMPRV-029 (list panes)
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Listings, Listing detail artboard)

## Working

- `App\Domain\Seller`: `ListingView` (list/table/grid, `default()`, `showsSort()`, `iconPath()`), `ListingSortColumn` (11 cases, `label()`, `alignsRight()`, `keyOf()`), `ListingSortDirection` (`flipped()`, `ariaSort()`), `ListingSort` (`default()`, `nextDirectionFor()`, `ariaSort()`), `ListingTableRow` (readonly row: price/revenue/stockLabel/conversion), `ListingTableSort::apply()` (stable usort).
- `App\Domain\Listings\ListingStatus` gained `sellerBadgeLabel()`/`sellerBadgeTint()`; `x-seller.listing-status-badge` now reads them instead of matching status+removal inline, so the table's Status column sorts by the same label the badge renders.
- `App\Analytics\AnalyticsReport::countsForListingsSince()`: the ranged, plural counterpart to `countsForListing()`, one `whereIn` query keyed back onto each listing id. The show route now reads through it (range default 30d) instead of the unranged `countsForListing()` for its detail figures; the admin listing page still uses the unranged form, untouched.
- `App\Seller\ListingTable`: `forSeller()` (table/grid rows) and `forListing()` (the detail's own row, reusing the same medium/sales/analytics joins so a listing's page never disagrees with its row in the table). Sold/revenue: `order_items` on a paid order (`OrderStatus::hasBeenPaid()`) whose matching `fulfillments` row is still live (not declined/refunded), all time.
- `App\Http\Requests\Seller\ListingsQueryRequest`: `view`, `from`, `sort`, `dir`, `range` for both the index and detail routes, 400 on an unrecognised value, defaults on absent/emptied.
- `ListingController`: `index()` renders list/table/grid behind one header; `show()` renders the classic pane+detail when `from` is absent, or `detail-overlay` (workspace hidden below `2xl`, `<dialog open>` at `2xl`+, a `2xl:hidden` takeover) when `from=table|grid`. Zero changes to `routes/seller.php` — the existing resource routes already covered index/show.
- `x-seller.listing-detail` extracted from the old `show.blade.php`; renders from a `Listing` + its `ListingTableRow` in the list pane, the overlay, and the takeover alike. New: Dimensions, Sold (count · revenue), a ranged "Views, last N days" bar strip (`x-seller.bar-strip`, mirroring the admin one). The old 3-column Day/Views/Favorites/Cart-adds table is gone, replaced by the strip — existing `ListingControllerTest` cases for it were rewritten against `row`/`strip`/`rangeDays`; the query-count test moved from 15 to 17 (net +2: +3 for `ListingTable::forListing`'s medium/sales/analytics reads, −1 for the removed unranged `countsForListing` read).
- Sort control: table headers are `<a href>` links carrying `aria-sort` (doctrine's "sorting is a link"); the header's own `<select name="sort">` (Grid has no headers to click) posts back to the index route by GET with a `<noscript>` submit fallback.
- `docs/seller-portal.md` created with just the Listings section, added to `docs/README.md`'s index.
- Left out / not fully explored: no visible `range` control in the header — the ticket's outcome names `range` as a query parameter with a 400 contract but the prototype's own header markup carries no range UI, so it is fully wired (default 30, threaded through every link) without a control to change it; a future ticket can add one. HTTP-level coverage of the overlay/takeover leans on presence assertions (`<dialog`, `2xl:hidden`) rather than a rendered-viewport check, since Pest has no CSS engine.
- `make check` (lint → assets → coverage) green: 4167 tests passed, coverage 99.5% (gate is `--min=95`; the codebase already sat below 100% on files this ticket never touched).

## Review pass

Merge review came back "merge after fixes"; all applied on the same branch.

- Sort determinism: `ListingTableSort::compare()` breaks a tie on equal sort keys by row id; `ListingTable::forSeller()`'s query carries `orderByDesc('created_at')->orderByDesc('id')`, the same order every other listings query uses. Domain test with three same-key rows, both directions.
- CSP: the sort `<select>`'s inline `onchange` is gone — `SecurityHeaders`' CSP has no `script-src 'unsafe-inline'` outside debug. Follows the `configurator-autosubmit.js` idiom: `data-sort-form`/`data-sort-select`/`data-sort-submit` hooks, a new `public/sort-autosubmit.js` the seller layout loads deferred, an always-rendered Sort button the script hides.
- `x-seller.listing-detail` takes a `placement` prop (`overlay`/`takeover`) that prefixes its two heading ids, since both copies sit in the DOM at once in `detail-overlay.blade.php` (one hidden by a `2xl:` breakpoint, not removed). The header that carries New listing now renders once, above both viewport-gated blocks, so it and its dialog stay reachable at every viewport instead of living inside the `2xl:`-gated workspace. The workspace behind the open `<dialog>` carries `inert`.
- `App\Domain\Seller\ListingView::openable()` (`[Table, Grid]`) is the one list both `ListingsQueryRequest`'s `from` rule and `ListingsQueryRequest::from(): ?ListingView` read, replacing a hand-written `FROM_VIEWS` array and the controller's separate `ListingView::from($from)` call that could 500 on drift.
- `App\Seller\ListingsChrome` (+ `ViewLink`, `ColumnHeader`) replaces the controller's four array-building methods (`workspaceChrome`/`viewLinks`/`sortOptions`/`columnLinks`, ~80 lines) and the blade's `ListingView::from($link['key'])` reconstruction; views read `$chrome->viewLinks`, `$chrome->columnHeaders`, `$chrome->sort`.
- `show()`'s `?from=` branch builds the seller's rows once (`ListingTable::forSeller`) and reads the opened listing's own row out of that list (`rowFor()`), instead of a second `ListingTable::forListing()` query duplicating the medium/sales/analytics reads.
- The seller `x-seller.bar-strip` component is gone (it only differed from `x-admin.analytics.bar-strip` in default height); `x-seller.listing-detail` renders through the admin one with `:height="72"` — `BarStripBar::hot` defaults false, so nothing admin-specific leaks in.
- `x-seller.listing-status-badge` renders through `x-seller.status-badge` instead of keeping its own tint-to-class match, so a listing's badge and its own row's status cell never read different sizes on the same screen.
- `ListingTable::mediumsByListing()` orders by id and keeps the first row per listing (matching `Listing::mediumAttributeLabel()`'s "first"); a `?->` null guard on `propertyValue` was tried and reverted — PHPStan refuses it as dead code, since the model's own docblock and the FK's `cascadeOnDelete()` already guarantee it.
- Refunded (not just declined) fulfillments now have their own `ListingTableTest` case, `RefundFulfillment` through an admin actor, asserting `sold === 0`.
- Weak test assertions fixed: the revenue test now asserts `assertViewHas('rows', ...)` on `sold`/`revenueCents` directly rather than matching `$680.00`, a string that also matched the row's own price; the grid-view test asserts `'0 views'` rather than the bare word `'views'`, which the page header could also satisfy. Added a structural test that the New-listing dialog's markup precedes both viewport-gated blocks (a proxy for "reachable", since Pest has no CSS engine) and a test that the overlay/takeover copies of the detail carry distinct heading ids.
- `colspan="11"` in `_table.blade.php` is now `count($chrome->columnHeaders)`; `aria-current="true"` in `_header.blade.php` is now `"page"`.
- Prose: dropped "rather than" clauses from `ListingSortColumn::keyOf()`'s docblock, `ListingsChrome::build()`'s docblock, and `ListingsQueryRequest`'s class and `prepareForValidation()` docblocks; a test's "Cheap Charm" listing became "Pygmy Puff".
- `make check` green: 4179 tests passed, coverage 99.5% (`--min=95`).

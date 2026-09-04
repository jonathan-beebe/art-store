---
id: FEAT-056
type: feature
status: open
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

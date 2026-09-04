---
id: FEAT-055
type: feature
status: open
created: 2026-09-03
---

# FEAT-055: The dashboard opens on three tiles, listing activity, and what needs attention

## Problem
The seller dashboard (`resources/views/seller/dashboard.blade.php`, `App\Http\Controllers\Seller\DashboardController`) is four counts, a listing status tally, and the last five notifications. It answers nothing about customers, sales over time, or which listings are moving, and it points nowhere.

## Goal
A seller opens the portal and sees their business in three numbers, sees which listings are drawing people, and sees what needs doing today, each one click from the tool that does it.

## Outcome
- A range control (7, 30, 90 days; default 30) governs the page; the caption names the store and the range.
- Three brand-icon tiles in the Tailwind Plus "with brand icon" shape: Customers (count of buyers, new in range), Orders (orders placed in range, change vs the previous range), Earnings (net in range, change vs the previous range). Each tile carries a sparkline of the range's daily series and a footer link: View customers, Manage orders (with the To ship count), See earnings (with the next payout date). The whole tile is the link.
- Activity on your listings: four shared-border stats (views, favorites, cart adds, sold) with change vs the previous range, then the top five listings by views with a daily bar strip, views, favorites, cart adds, sold; each row opens the listing.
- Needs your attention: four groups — orders to ship (oldest first, age in red past two days), messages waiting on you (unread buyer threads), the next payout (released so far, still held), listings that need work (drafts with publish issues, sold-out pieces) — each with a header link to its tool and rows that open the exact thing.
- Range is a query parameter; an unknown value answers 400. Empty states read as sentences, not blank panels. `make precommit` green; `make check` green before the PR.

## Why it matters
The dashboard is the first screen every session. Three numbers with direction, the listings that are working, and a to-do list drawn from the real queues turn the portal from a menu into a morning briefing.

## Discovery notes
- Reuse `App\Domain\Analytics\AnalyticsRange` and `RangeChange` for the range and the deltas; `AnalyticsReport` for per-listing counts (a plural, ranged `countsForListingsSince` is the cleanest addition); `BarStrip` for the strips.
- Tiles: `SellerOverview` in `App\Seller` returning the three numbers with previous values and daily series; the sparkline is an inline SVG polyline component fed with points computed in PHP.
- Focus groups: a pure `AttentionQueue` value object assembled from FEAT-053's To ship lane, the unread thread query the nav already runs, FEAT-060's `PayoutEstimate` (or `LedgerBalance` until it merges), and `Listing::publishIssues()` plus `sold` status.
- The "with brand icon" tile: `relative overflow-hidden rounded-lg bg-white px-4 pt-5 pb-12 shadow-sm sm:px-6 sm:pt-6`, icon `absolute rounded-md bg-indigo-500 p-3`, footer `absolute inset-x-0 bottom-0 bg-gray-50 px-4 py-4 sm:px-6`, dark variants alongside.

## Related work
- FEAT-053, FEAT-054, FEAT-060
- FEAT-044..048 (admin analytics ranges, deltas, strips)
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Dashboard)

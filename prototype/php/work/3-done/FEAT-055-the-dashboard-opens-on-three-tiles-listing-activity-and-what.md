---
id: FEAT-055
type: feature
status: resolved
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

## Working

### What landed

Route vocabulary: `GET /seller?range=7|30|90`, default 30, a bare 400 on
anything else. `App\Http\Requests\Seller\DashboardQueryRequest` owns it,
the docs/alignment.md §5 idiom the listings and customers requests already
hold.

Pure core (`App\Domain\Seller`):

- `Sparkline::of(counts, width, height)` — a daily series as one SVG
  polyline plus its last point, scaled against the series' own floor with a
  two-pixel inset. `BarStrip` is the same idea in bars.
- `AttentionQueue::build()` — the four focus groups from rows an adapter
  gathered: the heading each wears at each count, the sentence it shows
  holding nothing, and `SHIP_OVERDUE_DAYS` (2), the age past which a parcel
  reads in red. `AttentionRow`, `AttentionRows`, `AttentionGroup`,
  `AttentionLinks` carry the shapes.
- `ActivityTotal::between()` — a count paired with its `RangeChange`.
- `FeedIcon` gained `Pencil` and `Users`.

Adapters (`App\Seller`), one page concern each:

- `SellerOverview` → three `OverviewTile` values. Buyers from
  `SellerCustomers`; orders and earnings from one read of the seller's paid
  parcels across both ranges, folded by the UTC day of `orders.placed_at`.
- `ListingActivity` → four `ActivityTotal` values and five
  `OverviewListingRow` values over `ListingTable`'s own rows, sorted by
  `ListingTableSort` on Views descending.
- `NeedsAttention` → `list<AttentionGroup>`, each queue counted whole and
  read down to five rows.
- `DashboardChrome::rangeLinks()` → `list<SegmentLink>` for
  `x-seller.segmented`.

`App\Analytics\AnalyticsReport` gained `countsForListingsBetween()` — the
ranged form `countsForListingsSince()` now delegates to — and
`dailyViewsForListings()`, five listings' strips in one query.

Components: `x-seller.brand-tile` (the Tailwind Plus "with brand icon"
shape, the whole tile a link), `x-seller.sparkline`, `x-seller.change`,
`x-seller.attention-panel`. The bar strip, the stat tile, the segmented
control, and the seller chrome are the ones already there.

Docs: a `Dashboard` section in `docs/seller-portal.md`, between Support and
Data.

### Decisions and what was left

- **Publish issues are not read on the dashboard.** The ticket asked for
  "drafts with publish issues". `Listing::publishIssues()` costs five or
  more queries per listing, and the group shows up to five rows on the
  portal's most-visited page. The group lists every draft and every
  sold-out piece — the two states a listing sells nothing in — and the
  draft row says "Draft · not on the storefront yet". The listing's own
  publish panel, one click away, lists what a draft still needs.
- **A heading counts the whole queue; the panel shows five rows** and links
  the rest to the tool (`AttentionGroup::hidden()`). A seller reads the
  true size of the pile without a forty-row panel.
- **Two aggregates are read twice on purpose.**
  `Seller::escrowBalance()` folds once for the payout estimate and once for
  `HeldEscrow`'s total; the to-ship parcels are counted once for the orders
  tile's footer and once for the focus group's heading. Both are cheap, and
  threading either through would tie two adapters together for one query.
  Recorded in the docs section.
- **`RelativeTime::long()`** was extracted from `ParcelState`'s private
  elapsed-time helper, which now reads it, so "2 days ago" is written once.
  `App\Domain\Fulfillment` reading `App\Support\RelativeTime` passes the
  arch rules — the helper is pure, with no clock and no framework.
- **The nav chip carries `data-nav-count`.** The dashboard renders figures
  of its own, so `SellerLayoutComposerTest`'s `>1</span>` assertions no
  longer name the chip apart from the page. The chip has a hook now and the
  test reads that.
- The old dashboard's listing status tally, escrow tiles, and recent
  notifications are gone; the notifications page is unchanged and still
  reached from the bell.

### Follow-ups found

- `FulfillmentStatus::sellerBadgeTint()` is now read by the admin
  fulfillment views as well as the seller badge (FEAT-053 chore below). The
  name still says "seller"; renaming it touches every seller call site and
  was left.
- `NeedsAttention` guards against a thread with no message so PHPStan can
  read the body; the branch cannot be reached, and it is the one line of
  the class the coverage report leaves uncovered.

### The four integration chores

- `[MAINT-008]` `docs/seller-portal.md` now reads in nav order — Store
  profile, The public page, Listings, Orders, Activity feed, Customers,
  Messages, Earnings, Support, Dashboard, Data — and the intro table names
  every section on one line each, with the three it never named (Orders,
  Customers, Messages) added.
- `[MAINT-008]` `resources/help/seller/shipping.md` says step completions
  show on the order's activity feed again, with the carrier and tracking
  number a label step recorded. The other three articles were re-read
  against the merged branch: escrow on payment, release on delivery
  confirmation, Monday-to-Sunday payout periods, the 10% fee,
  title/price/quantity-or-made-to-order to publish, and Publish as FAQ
  resolving the thread all still hold.
- `[FEAT-052]` `App\Seller\OrderSource`'s refunded row led with the ledger
  entry's amount — the seller's net — under "returned to the buyer".
  `IssueRefund` sends the buyer the whole subtotal, so the row leads with
  that and the sentence names the net that left the seller's balance. One
  test pins both amounts.
- `[FEAT-053]` The admin fulfillment views each spelled their own
  status-to-tint match. All five pairs matched
  `FulfillmentStatus::sellerBadgeTint()` exactly, so both read the enum now
  and its docblock names both portals.

### The gate

`make check` green: lint (Pint + PHPStan, no errors), assets built, 5,164
tests, 99.5% coverage against a 95% floor.

---
id: FEAT-046
type: feature
status: resolved
created: 2026-09-02
---

# FEAT-046: the storefront funnel reads from listing view to paid order

## Problem

The analytics store records `listing.view`, `listing.favorite`,
`listing.unfavorite`, `listing.cart_add`, and page views
(app/Domain/Analytics/AnalyticsEventName.php). The steps after the cart —
checkout opened (`GET /checkout`, routes/shop.php:47), order placed
(`App\Actions\Orders\PlaceOrder`), order paid
(`App\Actions\Orders\FinalizeOrder`), order cancelled (`CancelOrder`) —
are commerce facts in the app database and never reach the store. The
admin analytics pages (FEAT-045) therefore show traffic and interest with
no denominator: nothing says how many viewers bought, or where along the
way they left.

## Goal

An admin reads the whole storefront funnel for a range, step by step, with
the conversion between steps and the range before it beside each number.

## Outcome

For a chosen range, an admin sees one funnel with these steps in order:
visitors, listing views, favorites, cart adds, checkouts opened, orders
placed, orders paid; each step shows its count, the rate from the previous
step, and the change against the range before; the same funnel is
available per listing on the listing's analytics page and per seller;
every checkout opened, order placed, order paid, and order cancelled by a
shopper is recorded in the analytics store as an event with its actor,
its order id as the subject, and the request facts every event carries,
and none of those recordings adds a write to the commerce database or
runs inside a commerce transaction; the recording of a paid order carries
the order's total in its data so a later revenue report can read it
without a join; the funnel numbers match the app database's own counts
of orders placed and paid for the range in a test; the docs and the
alignment contract name the new event names; the suite stays green.

## Why it matters

Views, favorites, and cart adds only mean something against paid orders.
A listing with many favorites and no cart adds has a price problem; one
with cart adds and no orders has a checkout problem. Without the last
three steps in the same store, the drill-in built in FEAT-045 answers
"what was popular" and never "what sold".

## Discovery notes

- The vocabulary is closed (`AnalyticsEventName`); the new names want the
  same shape: `checkout.open`, `order.place`, `order.pay`, `order.cancel`,
  with `subject_type = 'order'` from the place step onward and the listing
  ids in `data` (an order spans listings; the per-listing funnel needs
  them). Checkout opened has no order yet — the cart id is its subject.
- `Analytics::recordEvent()` buffers and flushes after the response, so a
  record inside `PlaceOrder`/`FinalizeOrder` costs the commerce transaction
  nothing; the story events `order.place`/`order.pay`
  (`App\Logging\StoryEvent`) mark where those actions already announce
  themselves. `AddToCart` and `ToggleFavorite` show the injection pattern.
- Visitors as the first step: distinct `session_id` (the `sid` cookie,
  `App\Support\RequestMarks`) or distinct `actor_id` with a page view in
  the range; pick one and say which in the docs. The `sid` cookie lives a
  year, so it counts visitors, not browsing sessions.
- The funnel is a natural third section on `/admin/analytics` above the
  events table, and a section on the listing and seller analytics pages;
  `EventTotals` and `EntityActivity` already compute per-range counts by
  name, so the funnel is a fixed ordering of those counts plus rates.
- Order cancelled belongs in the store for the funnel's honesty (a placed
  order that never pays) even though it is not a step.
- Node and Rails owe the same vocabulary once it lands in
  `docs/alignment.md` §2.6.

## Related work

- FEAT-039 — the analytics store and `Analytics` entry point
- FEAT-044 — request facts on every event
- FEAT-045 — the admin drill-in these numbers land on
- FEAT-003 — the order lifecycle actions the steps come from

## Working

Commits (stage A, already landed on this branch, plus stage B below):

- `0d511089` — the vocabulary gains `checkout.open`, `order.place`,
  `order.pay`, `order.cancel`
- `c295c9b1` — checkout, placed, paid, and cancelled orders record the
  events
- `fe9e524c` — `App\Analytics\Admin\Funnel`: `forRange()` / `forListing()`
  / `forSeller()`, `App\Domain\Analytics\FunnelRate`, the
  placed/paid-vs-`Order::query()` consistency test
- `635a31cc` — `x-admin.analytics.funnel` mounted on `/admin/analytics`,
  `/admin/analytics/listings/{listing}`, and the admin seller page
- `2deaf356` — an actor's feed names order and cart subjects instead of
  reading every subject as a listing

Definitions chosen (docblocks + `docs/analytics.md` § "The funnel" carry
the full text):

- Visitors: distinct `session_id` among the scope's own events, null
  session ids excluded. For a listing or seller scope this is sessions
  that touched that scope specifically (a listing's own view/favorite/cart-add
  rows, plus checkout/order rows whose `data.listing_ids` includes it) —
  not a share of site-wide traffic, so the rate below it reads as "how
  many of the people who touched this listing bought".
- Listing views, favorites, cart adds: event counts by name, scoped by
  `subject_type`/`subject_id` for a listing or seller funnel.
- Checkouts opened, orders placed, orders paid: event counts by name;
  scoped for a listing or seller funnel through `data.listing_ids` via
  SQLite's `json_each` (read-worse-in-PHP was not needed — the `EXISTS
  (SELECT 1 FROM json_each(...))` clause reads as one `WHERE` branch
  alongside the subject match, both in the same query).
- Orders cancelled: not a step — the paid step's `note` carries the
  range's cancelled count.
- Rate: `App\Domain\Analytics\FunnelRate`, the step's count over the step
  immediately before it *in funnel order* (cart adds' rate is read
  against favorites, not against views) — a whole percentage and the raw
  ratio, null on the first step.

Decisions:

- `Funnel`, `FunnelStep`, `FunnelView` live under `App\Analytics\Admin`
  (the page-shaped read layer, alongside `EventTotal`/`EventTile`);
  `FunnelRate` lives under `App\Domain\Analytics` (pure rate math,
  alongside `RangeChange`).
- The seller page's funnel has no range control (the ticket's own call);
  it always reads 30 days, stated in its own h2.
- `EntityActivity::forActor()`'s feed dispatches per row on
  `subject_type` now — `order`/`cart` join a `Listing::whereIn()` already
  covering listing subjects, so the page's query count is unchanged.
- `SellerMessageController`'s re-rendered seller page (its 429 trip
  response) needed the same funnel data `SellerController::show` passes —
  found by the full suite, not by inspection.

`make precommit`: 3760 tests passed (10804 assertions), lint clean.

---
id: FEAT-046
type: feature
status: open
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

---
id: FEAT-020
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-020: Admin dashboard, accounting, ledger browser, and site stats

## Problem
`/admin` lists links; there is no platform tally by status, no money summary, no per-seller reconciliation, no ledger browser, no page-view record at all, and `listing_events` writes one `view` row per GET with no collapse. `docs/alignment.md` §5 lists `/admin` (tallies for every status incl. zero rows, platform money), `/admin/accounting`, `/admin/ledger?seller=&type=`, `/admin/stats` (page views by day and route pattern, listing event tallies), and the roll-up rules.

## Goal
An operator can read the platform's state, money, and traffic from the admin site, from small tables.

## Outcome
`/admin` shows a tally for every listing/order/fulfillment status (zero rows still listed), platform money (held, available, paid out, fees earned, fees refunded, refunded), and page views this week; `/admin/accounting` reconciles every seller; `/admin/ledger` browses entries with folded totals for the filtered set; `/admin/stats` shows page views by day (7-day window) and by route pattern plus listing event tallies; page views are rolled up at response time into `page_view_counts (site, path_pattern, day, count)` by one upsert; a listing `view` event is collapsed to one per (listing, customer, UTC hour); tests cover the tallies with empty states, the roll-up upsert, and the collapse; `docs/admin.md` gains the two diagrams.

## Why it matters
Retro item 8 and the product notes ask for traffic and accounting; without roll-ups the tables grow with traffic and the admin pages get slower every day.

## Discovery notes
Node's `pageViewRollup` `onResponse` hook and `isRecordedOncePerHour` are the reference; in Rails a Rack middleware or an `after_action` in `ApplicationController` reading `request.route_uri_pattern` for the pattern and `upsert` on the unique `(site, path_pattern, day)` index. Group-by tallies merged over the enum's full value list keep zero states visible.

## Related work
- docs/alignment.md §5
- prototype/node FEAT-006

## Working

### What landed

**`page_view_counts` and the roll-up.** A new migration
(`20260824000103_create_page_view_counts`), `id: :string` via `prefixed_id
:pvc`, a unique index on `(site, path_pattern, day)`. Two pure modules mirror
Node's `core/analytics/`: `PageView` (`countable?`, `day`, `week`,
`site_for`) and `Tally` (`over`) — plain values in, an answer out, no
database. `PageViewCount` is the record; `.record!(path_pattern:, at:)` is
one `upsert` (`unique_by: %i[site path_pattern day]`,
`on_duplicate: Arel.sql("count = count + 1")`) — one statement, no read
first, pinned by a `count_queries` test. `PageViewRollup`
(`app/controllers/concerns/`) is an `after_action` included once on
`ApplicationController`; it reads `request.route_uri_pattern` for the
pattern and `response.media_type`/`response.status`/`request.request_method`
for the countability check, and never runs at all for a request no route
matched or one a `before_action` turned away — both read as "counted against
nothing" without any extra code.

**The `listing_events` `view` collapse.** `ListingEvent.recorded_once_per_hour?`
and `.view_window_start` (UTC hour floor) mirror Node's
`isRecordedOncePerHour`/`viewWindowStart`. `Listing#record_event!` checks a
private `collapsed_view?` before writing; on a collapse it returns `nil` and
writes no row. `Shop::ListingsController#show` already opened a `listing.view`
`Story` for the request, so it answers it: `story.did` on a recorded view,
`story.refused(..., level: :debug, ...)` on a collapse — see the Fix-up
subsection below.

**The tallies.** `Tally.over(enum.keys, group(:column).count)` — used for
listings, orders, fulfillments on `/admin`, and `listing_events` on
`/admin/stats`. Keys come from each enum's own declared order
(`Listing.statuses.keys`, etc.), so a status nobody has reached still renders
in its lifecycle position, at zero.

**`PlatformMoney`** (`.fold`) is `LedgerEntry.balance` beside three more
folds — `Fulfillment.fees_earned_cents`, `Fulfillment.fees_refunded_cents`,
`Refund.sum(:amount_cents)` — four statements total, read on both `/admin`
and `/admin/accounting`. **`SellerAccount`** (`.for_every_seller`) is the
per-seller version: `LedgerEntry.balances_by_seller` plus two grouped
`Fulfillment` fee sums and one grouped `Refund` sum, mapped over
`Seller.order(:created_at, :id)` — five statements regardless of seller
count. `LedgerEntry` gained `for_seller`/`with_type` scopes matching the
`with_status`/`for_seller` shape FEAT-019 already established.

**Four controllers.** `Admin::DashboardController#show` (rewritten — see
below), `Admin::AccountingController#show`, `Admin::LedgerController#index`
(`LedgerEntry.for_seller(@seller_id).with_type(@entry_type)`, and
`.balance` called on that same filtered relation — Active Record delegates
an unscoped class method invoked on a relation back through `scoping`, so
the fold runs over the filtered rows only), `Admin::StatsController#show`.
Routes added under `namespace :admin`: `GET /admin/accounting`,
`GET /admin/ledger`, `GET /admin/stats`. Views: a shared `_stat` partial
(the `<dl><dt>/<dd>` tile every section on `/admin`, `/admin/accounting` and
`/admin/stats` uses), row partials per table
(`admin/accounting/_account`, `admin/ledger/_entry`,
`admin/stats/_day_count`, `admin/stats/_pattern_count`), and nav links for
Accounting/Ledger/Stats in the admin layout.

### Decisions on ambiguities

1. **The dashboard's "People" section keeps seller and customer counts**
   beyond the three things §5 names for `/admin` (status tallies, money, page
   views). Node's `platformTallies` — the reference `docs/alignment.md` §5
   points at — carries `sellerCount` and verified/anonymous customer counts
   too, and its `home.ejs` renders them ahead of the status tallies. The old
   Rails dashboard listed every seller and every verified customer by name;
   that duplicated `/admin/sellers` and `/admin/customers` (built by
   FEAT-019) for no reason once real directories existed, so the rewrite
   keeps the counts and drops the name lists, matching Node's actual shape
   rather than either extreme.
2. **A route pattern is stored with Rails' trailing `(.:format)` stripped**
   (`/art/:slug`, `/admin`), matching Node's bare `/art/:slug` — see the
   Fix-up subsection below for why the first cut at this went the other way
   and what changed.
3. **The platform's `refunded` figure is `Refund.sum(:amount_cents)`** — the
   total handed back to customers — read beside `fees_refunded_cents` (the
   fee portion of that money the platform forwent) rather than folded into
   it. Node's reference `platform-money.ts` has neither field: the Node
   prototype has no `declined`/`refunded` fulfillment status yet (its
   `FULFILLMENT_STATUSES` is `awaiting_shipment | shipped | delivered`), so
   there was nothing there to mirror. Both figures are built from
   `docs/alignment.md` §5's literal list and §4.2's "`fees_earned_cents`
   over fulfillments that are not declined/refunded, and a
   `fees_refunded_cents` total beside it."
4. **`SellerAccount` does not carry Node's `reconciles` boolean** (a
   payout-sum-vs-ledger-paid-out cross-check). §5 asks `/admin/accounting`
   for "held / available / paid out / refunded, fees earned and refunded" —
   six figures, all built. A reconciliation flag is a Node feature this
   ticket's scope does not name; adding it would mean a `payouts` join this
   ticket has no test coverage plan for.
5. **The `count_queries` assertions build the "many" case with distinct
   parents** (`create_seller` called fresh per row, never reused), per
   FEAT-019's fix-up finding that a shared parent lets Active Record's query
   cache hide an N+1 that `count_queries` would otherwise catch.

### Query counts pinned

| Page | Statements, 1 seller with money | Statements, 5 sellers with money |
| --- | --- | --- |
| `/admin` | measured equal via `count_queries`; asserted with `assert_equal one, five` | — |
| `/admin/accounting` | same | — |
| `/admin/ledger` | same | — |

(Exact statement counts are not pinned to a literal number in the tests —
matching FEAT-019's own tests — only that the count for one seller with money
equals the count for five.)

### Tests

Minitest throughout; integration tests through the stack for every
controller and the roll-up itself. New files:
`test/models/page_view_test.rb`, `page_view_count_test.rb`, `tally_test.rb`,
`platform_money_test.rb`, `seller_account_test.rb`,
`test/controllers/admin/accounting_controller_test.rb`,
`ledger_controller_test.rb`, `stats_controller_test.rb`,
`test/controllers/concerns/page_view_rollup_test.rb`. Extended:
`test/models/listing_test.rb` (the collapse, the next-hour case, the
different-customer case, the `refused`-at-`debug` log line, and that
favorite/unfavorite/cart_add are never collapsed),
`test/controllers/admin/dashboard_controller_test.rb` (rewritten for the new
page).

Two pre-existing tests asserted the *old*, uncollapsed `view` behaviour
(`ListingTest#test_its_totals_add_up_its_own_events` recorded two views back
to back with no `at:`, landing in the same UTC hour;
`Seller::ListingsControllerTest`'s activity-page test called
`2.times { create_listing_event(listing, "view", 1.day.ago) }`, same issue).
Both now space their two view calls at least an hour apart
(`test/models/listing_test.rb`, `test/controllers/seller/listings_controller_test.rb`)
so they exercise the same behaviour they always meant to (two views, ergo two
rows) without depending on an uncollapsed write.

### Deviations from the contract

None on §5's paths or filter names. `/admin`'s money row shows all six
figures §5 names (held, available, paid out, fees earned, fees refunded,
refunded) in that order.

### Left out

Pagination on `/admin/ledger`'s entry list and `/admin/stats`'s pattern list
— folds within known gap 12 in `docs/review.md` (every admin list is
unpaginated; the storefront's `Page` value object is the shape to reuse).
Known gap 13 (no accounting page) is closed by this ticket and removed; the
old gap 14 (seeds exercise no decline/refund) is renumbered 13 and left open
with a note that it also means the seeded demo shows `/admin/accounting` and
`/admin/ledger` at their zero case for refunds.

### Numbers

Before: 1020 runs, 3679 assertions, 0 failures, 1953/1953 lines (100%).
After: 1100 runs, 3915 assertions, 0 failures, 2044/2044 lines (100%).
`make lint`: 271 files inspected, no offenses.

### Fix-up

Review of `801d30d` found one blocker and two should-fixes.

**Blocker: a collapsed listing view logged both `refused` and `did`.**
`Listing#record_event!` returning `nil` on a collapse and
`Shop::ListingsController#show` calling `story.did` unconditionally meant a
collapsed view wrote `will`, then a raw `Rails.logger.debug` `refused` line,
then `did` — three lines for one `listing.view` story, and a `did` fired
whether or not a view was actually recorded. Fixed by:

- `Story#refused` now takes a `level:` override (`def refused(message,
  level: nil, **data)`), stored per-instance and preferred over `LEVELS` in
  `#write`. `LEVELS` itself is untouched — `refused` still defaults to
  `:info` for every caller that does not ask for something else.
- `Shop::ListingsController#show` checks `record_event!`'s return value: a
  recorded view calls `story.did`, a collapse calls
  `story.refused("collapsed a repeat view within the hour", level: :debug,
  listing_id:, slug:, customer_id:)`. One `Story.tell` now ends in exactly
  one line either way.
- The raw `Rails.logger.debug` call is deleted from `Listing#record_event!`;
  it returns `nil` on a collapse and writes nothing itself. The `RateLimiting`
  bypass is untouched — it fires from a `before_action` with no enclosing
  `Story`, which is not this situation.

The gap that let three lines through unnoticed: the only existing test
called `Listing#record_event!` directly from a model unit test, so it never
went through the controller. That test (`"a collapsed view logs listing.view
refused at debug"` in `listing_test.rb`) is removed — the model no longer
logs anything to assert on — and replaced by an integration test in
`shop/listings_controller_test.rb` (`"a second view within the hour ends the
story once, refused at debug"`) that hits `GET /art/:slug` twice with
distinct `X-Request-Id` headers and asserts, from the captured log lines,
that each request produces exactly one `listing.view` ending (`did` then
`refused`), that the second is `phase: "refused"` at `level: "debug"`, and
that it carries `request_id` (the second request's own), `session_id`
(shared with the first request, same browser), `actor_id`, and `txn_id`.

`docs/admin.md`'s claim that a collapsed view "is not a unit of work that
failed, so there is no `will` line for it to answer" was wrong — the
enclosing `Story.tell` in the controller writes a `will` line on every
request regardless of outcome. Corrected to describe the collapse as one of
two endings the same story can reach, not a second story with nothing to
answer.

**Should-fix: `(.:format)` stripped from the stored `path_pattern`.** The
original decision kept Rails' `route_uri_pattern` verbatim on the grounds
that it was the pattern's "canonical form" — but the root route renders bare
`/` while every other route carries `(.:format)`, so that canonical form was
already inconsistent, and the code already special-cased root to live with
it. `docs/alignment.md` §5 fixes `page_view_counts` as a shape shared with
Node, which stores the bare pattern; a constant suffix on every Rails row
worked against a reader comparing the two tables. Fixed in
`PageViewRollup#roll_up_page_view` (`pattern.sub(/\(\.:format\)\z/, "")`
before `PageViewCount.record!`), which let `PageView.site_for` drop its `"("`
special case back to a plain prefix match. Updated
`page_view_rollup_test.rb`, `page_view_count_test.rb`, `page_view_test.rb`,
`docs/admin.md`, and `docs/data-model.md` to the bare pattern.

**Should-fix: a `HEAD` request counted as a page view.** Rails rewrites a
`HEAD` to a `GET` before the controller runs and `request.request_method`
returns the rewritten value, so `PageViewRollup` counted a crawler's `HEAD
/art/:slug`. `ActionDispatch::Request#method` (no args) returns the
un-rewritten value Rack received (`rack.methodoverride.original_method` when
Rails set it, else the raw `REQUEST_METHOD`) — `roll_up_page_view` now reads
`request.method` instead of `request.request_method` for the countability
check. Added `"a HEAD request is not counted, even though Rails answers it
with a GET's body"` to `page_view_rollup_test.rb`.

**Nit: compact the data hash.** Moot — removing the raw `Rails.logger.debug`
call left no hand-built `data:` hash in the new code; `story.refused`'s
keyword args flow through `Story#write`'s existing `.compact`.

Numbers after the fix-up: 1102 runs, 3924 assertions, 0 failures, 2046/2046
lines (100%). `make lint`: 271 files inspected, no offenses.

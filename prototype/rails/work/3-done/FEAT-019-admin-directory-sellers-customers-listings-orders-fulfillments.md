---
id: FEAT-019
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-019: Admin directory — sellers, customers, listings, orders, fulfillments

## Problem
The admin site (`FEAT-009`) has a sellers list with a listing count, a verified-only customers list (anonymous rows 404), a customer detail with an order count, and messaging. It has no seller detail with listings/fulfillments/payouts/balance, no customer standing filter or merge history, no favorites/cart on the customer detail, no cross-seller listings list or detail, no orders or fulfillments lists or details. `docs/alignment.md` §5 lists the pages and filters the Node admin site answers and the other two adopt.

## Goal
An operator can find and read any seller, customer (anonymous included), listing, order, or fulfillment on the platform from the admin site.

## Outcome
`/admin/sellers/:id` shows listings, fulfillments, payouts, and the folded balance; `/admin/customers?standing=all|verified|anonymous|blocked` and the detail with orders, favorites, cart, block and merge history; `/admin/listings?status=&seller=&removed=` and `/admin/listings/:id`; `/admin/orders?status=&customer=` and `/admin/orders/:id` with items, payments, fulfillments, refunds; `/admin/fulfillments?status=&seller=` and `/admin/fulfillments/:id`; every filter optional with empty meaning all; balances folded once per page; every page 404s on an unknown id; tests per page and filter, with a `count_queries` assertion on each list; `docs/admin.md` written.

## Why it matters
The alignment brief says the three prototypes support the same CX; the admin site is where Rails is furthest behind Node.

## Discovery notes
Node's `docs/admin.md` (on `main`) is the reference for pages, filters, and the folded-balance rule. Rails idiom: resourceful controllers under `Admin::`, scopes for filters, `includes` for the counterpart rows, partials for the tables, the existing admin `before_action` guard.

## Related work
- docs/alignment.md §5
- FEAT-009

## Working

### What landed

Ten routes under `namespace :admin`, each segment constrained by
`PrefixedUlid.constraints(...)` for its own table:

| Path                                                              | Controller                                                |
| ----------------------------------------------------------------- | --------------------------------------------------------- |
| `/admin/sellers`, `/admin/sellers/:id`                            | `Admin::SellersController` (index added, show extended)   |
| `/admin/customers?standing=`, `/admin/customers/:id`              | `Admin::CustomersController` (index added, show extended) |
| `/admin/listings?status=&seller=&removed=`, `/admin/listings/:id` | `Admin::ListingsController` (new)                         |
| `/admin/orders?status=&customer=`, `/admin/orders/:id`            | `Admin::OrdersController` (new)                           |
| `/admin/fulfillments?status=&seller=`, `/admin/fulfillments/:id`  | `Admin::FulfillmentsController` (new)                     |

Filters are Active Record scopes whose bodies return `nil` for an absent
value, so the relation falls back to `all`: `Listing.with_status`,
`Listing.for_seller`, `Listing.removal_standing`, `Order.with_status`,
`Order.for_customer`, `Fulfillment.with_status`, `Fulfillment.for_seller`,
`Customer.standing`. `Admin::BaseController` reads them with
`optional_filter` / `filter_from` / `id_filter`; the guard stays the single
`before_action :require_admin!` it already was.

The folded balance is `Seller.directory`, which reads
`LedgerEntry.balances_by_seller` (one grouped statement) plus two grouped
counts and one seller select, and hands the view `Seller::Row` values. It
never calls `Seller#escrow_balance` per row. `Customer.directory` has the same
shape for its three counts.

Repeated markup is four partials — `admin/shared/_table`,
`admin/shared/_empty`, `admin/shared/_filters` — plus a row partial per
resource (`admin/listings/_listing`, `admin/orders/_order`,
`admin/fulfillments/_fulfillment`, `admin/sellers/_row`,
`admin/customers/_row`), reused between the index pages and the detail pages'
sections. The admin layout's nav carries all five directories.

`docs/admin.md` is written and linked from `docs/README.md`; `docs/review.md`
gains a directory row and known gap 12 (the lists are unpaginated).

### Decisions on ambiguities

1. **An unknown filter value is 400 Bad Request.** `?standing=wat`,
   `?status=wat`, `?removed=wat` and an id filter carrying another table's
   prefix all raise `ActionController::BadRequest`. Node has an answer here —
   its route `querystring` zod schema refuses the value and turns it into
   400 — so Rails matches the status code. It does not match the rendering:
   Node's `plugins/error-pages.ts` renders the 400 inside the site's own
   layout, while nothing in `Admin::BaseController` or its ancestors rescues
   `ActionController::BadRequest`, so Rails serves the static, un-themed
   `public/400.html` — the same page every site falls back to, with no admin
   nav. No site in this app renders its own 404 or 400 today (see
   `docs/review.md`'s known gaps), so this is not a regression FEAT-019
   introduced; it is out of this ticket's scope to fix. Not 404: the page
   exists, the query string is what does not. Not "treated as all": a filter
   that silently widens is a lie about what the operator is reading.
2. **An id filter naming nobody is not an error.** `?seller=sel_<unused>` is a
   well-formed id, so it narrows to nothing and renders the empty state, the
   way Node's `idValue` does.
3. **`/admin/customers/:id` now answers for an anonymous customer.** It used
   to be `Customer.verified.find`, which 404'd a browser holding a cart.
   §5 says the customers list includes the anonymous rows and Node's
   `customerDetail` takes any customer, so the detail follows. The Message
   button is hidden for a customer with no address —
   `Admin::CustomerConversationsController` still refuses one, since there is
   nobody to write to. The dashboard's customer list is unchanged (still
   verified only); `FEAT-020` rewrites that page.
4. **`Seller::Row` / `Customer::Row` are `Data` values, not a query object
   layer.** They exist because a directory row is a seller plus three numbers
   the seller does not know, and the alternative is three hashes indexed by id
   in the view. No service objects were added.
5. **The `status` filter tests walk every enum value in one test** rather than
   one test per value (eight for orders, four for listings, three for
   fulfillments). Each value is asserted present under its own filter and
   absent under another. The small, semantically distinct filters —
   `standing`, `removed` — do get a test per value, plus the empty case and
   the invalid case.

### Query counts pinned

Every list carries a `count_queries` assertion that renders the page with one
row and again with five and asserts the counts are equal. Measured (including
the two the sign-in session costs):

| Page                  | Statements, 1 row | Statements, 5 rows |
| --------------------- | ----------------- | ------------------ |
| `/admin/sellers`      | 6                 | 6                  |
| `/admin/customers`    | 4                 | 4                  |
| `/admin/listings`     | 4                 | 4                  |
| `/admin/orders`       | 6                 | 6                  |
| `/admin/fulfillments` | 4                 | 4                  |

### Deliberately left out, and where it attaches

No write actions, per the ticket. The sections they hang from are built and
render an empty state:

| Action                            | Page, section                                                               | Ticket   |
| --------------------------------- | --------------------------------------------------------------------------- | -------- |
| Cancel an unpaid order            | `/admin/orders/:id`, beside the status in the header `dl`                   | FEAT-017 |
| Refund a fulfillment              | `/admin/orders/:id` Fulfillments rows and `/admin/fulfillments/:id` Refunds | FEAT-017 |
| Remove a listing / lift a removal | `/admin/listings/:id` Removal history                                       | FEAT-021 |
| Block a customer / lift a block   | `/admin/customers/:id` Block history                                        | FEAT-021 |
| Run the weekly payout             | `/admin/payouts` (not built here)                                           | FEAT-021 |

The `refunds`, `listing_removals` and `customer_blocks` tables are **not**
created here. Four one-line model methods and three scopes stand in their
place, each a drop-in replacement for a `has_many` or a `where`:

- `Order#refunds` → `[]`; `Fulfillment#refunds` → `[]` (FEAT-017 replaces both
  with `has_many :refunds`)
- `Listing#removals` → `[]`, `scope :removed, -> { none }`, and
  `scope :visible, -> { all }`; `Listing#actively_removed?` already reads
  `removals.any?`, so the storefront predicate `Listing#on_storefront?` needs
  no change either (FEAT-021)
- `Customer#blocks` → `[]`, `Customer#blocked?` → `blocks.any?`, and
  `scope :blocked, -> { none }` (FEAT-021)

No page changes when those tables land — only the seven model lines above.
`visible` is not a drop-in replacement the way the other six are, though:
`removal_standing`'s `visible` branch calls it, and `admin/listings?removed=visible`
reads that branch to mean "no removal stands over this listing." `-> { all }`
answers that correctly only because nothing removes a listing yet. Once
`listing_removals` is real, `visible` has to become a scope that excludes a
listing a removal stands over (e.g. `where.not(id: Removal.select(:listing_id))`)
— leaving it as `all` would make `removed=visible` show removed listings
alongside the rest.

### Deviations from the contract

None on §5's paths, filter names or filter values. Two notes:

- §5's `/admin/orders/:id` row says "actions (§4.4)" and
  `/admin/fulfillments/:id` says "detail with refund action". Those actions
  are FEAT-017's by this ticket's own scope; the pages exist and answer, the
  buttons do not.
- `/admin`, `/admin/accounting`, `/admin/ledger`, `/admin/payouts`,
  `/admin/stats` and the moderation POSTs are untouched — FEAT-020 and
  FEAT-021.

### Incidental

`Seller::BaseController#own_items(order)` is gone, replaced by
`Fulfillment#items`. All three of its callers already held the fulfillment,
and the admin fulfillment page needed the same answer; a second copy of the
same `select` on the controller was the alternative. `ShopHelper#money` moved
to `ApplicationHelper` — the admin views format cents too.

### Numbers

Before: 809 runs, 2689 assertions, 0 failures, 1587/1587 lines.
After: 882 runs, 2956 assertions, 0 failures, 1706/1706 lines. `make lint`
clean.

### Fix-up

A review of `f72b9f5` found three defects:

1. **The `count_queries` N+1 guard was structurally blind on three list
   tests.** The orders, listings, and fulfillments "5 rows" cases each built
   their rows against a single shared parent (one customer, one seller, one
   seller) — `ActiveRecord::QueryCache` serves the repeated identical
   `SELECT … WHERE id = <same value>` from cache, and `count_queries` excludes
   `payload[:cached]`, so dropping the `.includes(:customer)` /
   `.includes(:seller)` from those index actions would leave the pinned
   counts equal and the test green. Fixed by giving each of the three tests
   five distinct parents (`create_verified_customer` / `create_seller` called
   fresh for each row instead of reused). Verified the guard now bites:
   removing the parent `.includes` from each of the three index actions in
   turn (keeping the other `.includes` args) and rerunning that one test
   failed every time —
   `Admin::OrdersControllerTest#test_the_list_costs_the_same_however_many_orders_it_holds`
   (9 expected, 13 actual with `.includes(:customer)` dropped),
   `Admin::ListingsControllerTest#test_the_list_costs_the_same_however_many_listings_it_holds`
   (failed with `.includes(:seller)` dropped), and
   `Admin::FulfillmentsControllerTest#test_the_list_costs_the_same_however_many_fulfillments_it_holds`
   (failed with `.includes(:seller)` dropped) — then restored each `.includes`
   and confirmed green again. `Admin::SellersController#index` and
   `Admin::CustomersController#index` were checked and are sound as they
   stand: `Seller.directory` / `Customer.directory` fold every per-row count
   into one grouped query rather than preloading a nested association, and
   their `count_queries` tests already build distinct sellers/customers per
   row (no shared parent to begin with). Sanity-checked by swapping one
   grouped count for a per-row `seller.listings.count` /
   `customer.orders.count` in each — both breakages were caught (the test
   failed) without any test change needed, then reverted.
2. **The 400 for an unknown filter value is not the admin site's own page.**
   `Admin::BaseController#filter_from` / `#id_filter` raise
   `ActionController::BadRequest`; nothing rescues it anywhere in the
   controller tree and there is no `config.exceptions_app`, so it falls
   through to Rails' static `public/400.html` — no admin nav, no admin
   layout, shared by all three sites. Checked how 404 is handled first, per
   the review: it is the same story. `ActiveRecord::RecordNotFound` is never
   rescued either, and there is no per-site 404 template or handler anywhere
   in `app/controllers/` — every site, not just admin, falls through to the
   static `public/404.html`. So no site in this app renders its own error
   page today; matching Node's `plugins/error-pages.ts` (which renders both
   inside the site's own layout) would mean building an error-page mechanism
   for all three sites, which is out of scope for a ticket that touches only
   the admin directory. Chose (b): corrected the overstated claim in
   "Decisions on ambiguities" #1 above (it cited `plugins/error-pages.ts` as
   something Rails "matches" — true of the status code, not the rendering),
   corrected the same claim in `docs/admin.md`, and added it as known gap 11
   in `docs/review.md` (the old gap 11, unpaginated lists, is now 12).
3. **Two nits in `docs/admin.md` / this ticket.** "Where the write actions
   attach" said "Three model methods stand in their place" and then named
   four (`Order#refunds`, `Fulfillment#refunds`, `Listing#removals`,
   `Customer#blocks`) — corrected to "Four". And "No page changes when those
   tables land — only the six model lines" omitted `Listing.visible`
   (`scope :visible, -> { all }`): once `listing_removals` is real, `visible`
   is what `removed=visible` reads as "no removal stands over this listing,"
   and `-> { all }` only answers that correctly because nothing removes a
   listing yet — left as `all`, `removed=visible` would show removed listings
   alongside the rest. Corrected the count to seven and added the caveat in
   both this ticket's "Deliberately left out" section and `docs/admin.md`, so
   `FEAT-021`'s worker knows `visible` needs real logic, not just a rename.

`make check`: 882 runs, 2956 assertions, 0 failures, 1706/1706 lines, `make
lint` clean — unchanged from before the fix-up, since the changes were test
setup, documentation, and a verification exercise that left production code
as it was.

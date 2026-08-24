---
id: FEAT-019
type: feature
status: open
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

| Path | Controller |
| --- | --- |
| `/admin/sellers`, `/admin/sellers/:id` | `Admin::SellersController` (index added, show extended) |
| `/admin/customers?standing=`, `/admin/customers/:id` | `Admin::CustomersController` (index added, show extended) |
| `/admin/listings?status=&seller=&removed=`, `/admin/listings/:id` | `Admin::ListingsController` (new) |
| `/admin/orders?status=&customer=`, `/admin/orders/:id` | `Admin::OrdersController` (new) |
| `/admin/fulfillments?status=&seller=`, `/admin/fulfillments/:id` | `Admin::FulfillmentsController` (new) |

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
gains a directory row and known gap 11 (the lists are unpaginated).

### Decisions on ambiguities

1. **An unknown filter value is 400 Bad Request.** `?standing=wat`,
   `?status=wat`, `?removed=wat` and an id filter carrying another table's
   prefix all raise `ActionController::BadRequest`. Node has an answer here —
   its route `querystring` zod schema refuses the value and
   `plugins/error-pages.ts` turns a `ZodError` into 400 — so Rails matches it.
   Not 404: the page exists, the query string is what does not. Not "treated
   as all": a filter that silently widens is a lie about what the operator is
   reading.
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

| Page | Statements, 1 row | Statements, 5 rows |
| --- | --- | --- |
| `/admin/sellers` | 6 | 6 |
| `/admin/customers` | 4 | 4 |
| `/admin/listings` | 4 | 4 |
| `/admin/orders` | 6 | 6 |
| `/admin/fulfillments` | 4 | 4 |

### Deliberately left out, and where it attaches

No write actions, per the ticket. The sections they hang from are built and
render an empty state:

| Action | Page, section | Ticket |
| --- | --- | --- |
| Cancel an unpaid order | `/admin/orders/:id`, beside the status in the header `dl` | FEAT-017 |
| Refund a fulfillment | `/admin/orders/:id` Fulfillments rows and `/admin/fulfillments/:id` Refunds | FEAT-017 |
| Remove a listing / lift a removal | `/admin/listings/:id` Removal history | FEAT-021 |
| Block a customer / lift a block | `/admin/customers/:id` Block history | FEAT-021 |
| Run the weekly payout | `/admin/payouts` (not built here) | FEAT-021 |

The `refunds`, `listing_removals` and `customer_blocks` tables are **not**
created here. Four one-line model methods and two scopes stand in their place,
each a drop-in replacement for a `has_many` or a `where`:

- `Order#refunds` → `[]`; `Fulfillment#refunds` → `[]` (FEAT-017 replaces both
  with `has_many :refunds`)
- `Listing#removals` → `[]` and `scope :removed, -> { none }`;
  `Listing#actively_removed?` already reads `removals.any?`, so the storefront
  predicate `Listing#on_storefront?` needs no change either (FEAT-021)
- `Customer#blocks` → `[]`, `Customer#blocked?` → `blocks.any?`, and
  `scope :blocked, -> { none }` (FEAT-021)

No page changes when those tables land — only the six model lines.

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

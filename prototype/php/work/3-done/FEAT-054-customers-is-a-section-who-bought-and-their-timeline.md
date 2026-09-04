---
id: FEAT-054
type: feature
status: resolved
created: 2026-09-03
---

# FEAT-054: Customers is a section: who bought, and their timeline

## Problem
A seller has no view of the people who buy from them. Buyers exist only as `shipping_name` and `email` on individual fulfillments (`resources/views/seller/orders/show.blade.php`); there is no count, no list, no "how many times has this person ordered", and no way to reach an order or a thread from the person.

## Goal
A seller can see everyone who has bought from them, how much and how often, and everything that passed between them and one buyer.

## Outcome
- The left rail gains Customers between Orders and Messages, with the same nav idiom and active state.
- Customers lists every customer with at least one live fulfillment with this seller (declined and refunded fulfillments do not make a customer): name, email, orders, spent, favorites of this seller's listings, last order, conversations, since. Every column sorts by link with `aria-sort`; a segment control narrows to All, Repeat buyers (two or more orders), New this period (first order inside the range). Four tiles above: customers (with new-this-period), repeat buyers (with share), average order, open conversations.
- A customer page shows identity (name, email, since, Repeat buyer badge), a Message button that opens or starts the conversation, four tiles (orders, spent, favorites, conversations), the activity timeline (FEAT-052) with the kind filter, their orders with this seller, their favorites of this seller's listings, and their conversations.
- A customer who never bought from this seller answers 404 on the customer page. Range, segment, sort, and kind are query parameters; unknown values answer 400.
- The ontology names a seller's customer as a buyer. `make precommit` green; `make check` green before the PR.

## Why it matters
The brief's first dashboard tile is the customer count and it opens "into a customer portal". A seller who can see repeat buyers, who favorited what, and the last thing they said, treats people as people; the platform can build nothing for retention until this list exists.

## Discovery notes
- Derived, never stored: `SELECT customer_id ... FROM fulfillments WHERE seller_id = ? AND status NOT IN (declined, refunded) GROUP BY customer_id`, with aggregates from `fulfillments.subtotal_cents`, `favorites` joined to the seller's listings, and `conversations` by (seller_id, customer_id). Names and emails come from `customers` (verified) or the latest order's `shipping_name`/`email`.
- Suggested adapter `App\Seller\SellerCustomers` returning readonly rows; domain `CustomerSegment` and `CustomerSort` enums own the vocabulary and the 400 through a FormRequest.
- Sorting in SQL is fine here; the aggregates are all on the app connection.
- The seller nav lives in `components/layouts/seller.blade.php` (`$navLinks`) and the shared partial; the users icon path is in the design canvas script (`I.users`).
- The timeline is `ActivityFeedReader` with the (seller, customer) scope.

## Related work
- FEAT-052 (activity feed)
- docs/ontology.md, prototype/php/docs/ontology.md — "Customer" gains the seller's meaning
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Customers)

## Working

Design decided before the first test:

- `App\Seller\SellerCustomers` derives the list from `fulfillments` (live
  statuses only), joins favorites and conversations by id in PHP, and hands
  back `list<App\Domain\Seller\CustomerRow>`. `forCustomer()` is the
  one-customer method the orders workspace and the thread rail read.
- `App\Domain\Seller\{CustomerRow,CustomerSegment,CustomerSortColumn,
  CustomerSort,CustomerTableSort,CustomerTally}` are pure.
- `App\Http\Requests\Seller\CustomersQueryRequest` owns `range`, `segment`,
  `sort`, `dir`, `kind` and answers a bare 400 on an unrecognised value.
- `App\Seller\CustomersChrome` builds the segment links and the sortable
  column headers, the `ListingsChrome` idiom.
- A customer with no live fulfillment with the seller answers 404, which is
  also the privacy rule: browsing, favoriting, and asking do not open a
  person's page to a seller.

### What landed

- Nav: Customers between Orders and Messages, same idiom and active state.
- `GET /seller/customers` — four figures, `?segment=`, every column sorting
  by link with `aria-sort`, `?range=` deciding what "new this period" means.
- `GET /seller/customers/{customer}` — identity with a Repeat buyer badge,
  Message, four figures, the FEAT-052 feed under `?kind=`, orders,
  favorites, conversations. 404 for a customer who never bought here.
- `POST /seller/customers/{customer}/messages` — the buyer's newest thread,
  else the thread for their latest parcel through `OpenConversation`.
- `docs/seller-portal.md` gains a Customers section.

### Decided along the way

- `ListingSortDirection` became `SortDirection` and `App\Seller\ColumnHeader`
  took a `SortableColumn`, so the two tables share one direction enum and one
  header value object instead of the customers table copying both.
- `FulfillmentStatus::sellerBadgeTint()` and `Fulfillment::itemLabel()` took
  the two facts `x-seller.fulfillment-cells` spelled out inline, and
  `x-seller.thread-tag` took the inbox row's kind pill. The customer page
  reads all three.
- The orders list on a customer page keeps declined and refunded parcels,
  which the figures above leave out. A seller has to be able to look back at
  a parcel they turned down.
- The Message button opens an existing thread or a fulfillment thread; no new
  `ConversationKind` for a seller writing to a buyer out of the blue.

### Left alone

- No range control on the screen. `?range=` is honored and round-tripped;
  the prototype's Customers artboard carries the segment control alone, and
  the dashboard tile is what arrives carrying a range. The footnote under
  the table says what window "new" counts.
- No pagination. The list is every buyer, the way the prototype reads it.
- `seller/orders/show.blade.php` still spells its own status-tint match out;
  FEAT-053 rewrites that file, so it was left for that lane rather than
  edited underneath it.

### Gate

`make precommit` green on every commit; `make check` green before the PR.

### Review pass

Coordinator review, merge after fixes. What changed:

- The derivation gates on the order having been paid. A `fulfillments` row
  exists from placement, so an abandoned checkout made a customer and added
  to Spent; the pair of conditions is now the one `ListingTable` counts a
  sale by. Tested: a placed-and-never-paid order yields no customer.
- The figures are one grouped query — count, sum, first and last
  `orders.placed_at`, grouped by customer — plus one query for the latest
  order's name and address, run only for a buyer whose own account holds
  neither. The PHP fold over every parcel is gone.
- A buyer with one live and one settled parcel counts one order and one
  subtotal, and the settled parcel still lists under Orders on their page.
  Declined and refunded both covered.
- `CustomerTableSort` negated the id tie-break along with the column, so
  tied rows swapped between the two directions of one column. The direction
  applies to the column alone now.
- The Message button reads recency as `orders.placed_at`, the way the rest
  of the section does; tested with two parcels placed out of insertion
  order.
- `ConversationKind::tagLabel()`/`tagTint()` own the thread pill's words and
  colour; `x-seller.thread-tag` maps a tint to classes. The listings table
  renders its headers through `x-seller.sortable-th`.
  `App\Domain\Seller\Initials::of()` is the one avatar reduction.
- Comments and the doc section state the positive.
- The tile figures carry `data-stat` hooks and the tests read them; header
  and segment links are parsed through `Tests\QueryString` (the seller
  inbox's own `sellerRowQuery()` reads it too). The fourteen-case sort
  dataset that only asserted 200 gave way to one asserting an order.
- Blade calls no clock: the customer page takes `$now` from the controller.

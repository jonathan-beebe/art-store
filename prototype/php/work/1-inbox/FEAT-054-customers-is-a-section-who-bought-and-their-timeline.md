---
id: FEAT-054
type: feature
status: open
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

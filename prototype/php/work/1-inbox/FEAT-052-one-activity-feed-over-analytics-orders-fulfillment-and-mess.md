---
id: FEAT-052
type: feature
status: open
created: 2026-09-03
---

# FEAT-052: One activity feed over analytics, orders, fulfillment, and messages

## Problem
The seller portal shows a buyer's story in four unrelated places: analytics events on the admin listing and actor pages (`app/Analytics/Admin/EntityActivity.php`), order and payment facts on the order page, fulfillment timestamps as a `dl`, and messages in the inbox. No seller page lists, in time order, that a buyer viewed a piece, favorited it, asked a question, paid, and got their parcel.

## Goal
A seller can read everything that happened between them and one buyer, or on one order, as a single feed in time order.

## Outcome
- A feed exists for two scopes: one fulfillment, and one (seller, customer) pair.
- A feed row carries when it happened, who did it, what happened, and, for a message or a decline, the words themselves. Kinds: browsing (listing viewed, favorited, unfavorited, added to cart, checkout opened), order (placed, payment approved or declined, held in escrow, released, refunded), shipping (each completed flow step including the label with its carrier, shipped, delivered, declined), messages (each message in a thread with that customer; for an order scope, only threads about that order or its listings).
- Rows are newest first; a filter narrows the feed to one kind; the filter never changes what the sources return, only what is shown. Unknown kinds answer 400.
- A Blade component renders a feed in the seller chrome in the Tailwind Plus "feed" shape (32px round icon on a rail, body, time) and is the only feed markup in the portal.
- Merging and filtering are pure and unit tested without the database; each source is tested through the database. `docs/seller-portal.md` § "Activity feed" says which source owns which row.

## Why it matters
"Literally all activity" is the brief's own phrase for the order page. Sellers answer "did they get it?" and "what did I tell them?" a dozen times a day; today that means four tabs and memory. One feed over the same rows the admin already reads makes the order and the customer pages honest.

## Discovery notes
- Pure core under `App\Domain\Seller`: `ActivityFeed::merge(FeedEvent[] ...$sources)` newest first with a stable tie order; `FeedEvent` readonly (`occurredAt`, `kind`, `actor`, `text`, `quote`, `link`); `ActivityKind` enum with `label()`.
- Adapter under `App\Seller`: an `ActivityFeedSource` interface (`events(FeedScope): FeedEvent[]`) with `AnalyticsSource` (the analytics connection; `AnalyticsReport` and `EntityActivity` show the joins; `data.listing_ids` for checkout/order events), `OrderSource` (orders, payments, ledger entries), `FulfillmentSource` (FEAT-051's `fulfillment_events`), `MessagingSource` (messages in conversations with that customer); `ActivityFeedReader` composes them. `FeedScope` is a small readonly value object.
- The feed component can mirror the admin analytics feed markup listed in `__local__/design/admin-analytics/BUILD-BRIEF.md` (feed row shape) in the seller's gray/indigo palette; icons are inline SVG paths per kind, the same heroicons the admin uses.
- No page adopts the feed in this ticket beyond a rendering test; FEAT-053 and FEAT-054 place it.

## Related work
- FEAT-051 (fulfillment event log) — the shipping source reads it
- FEAT-044..048 (admin analytics drill-in) — the analytics readers and the feed markup
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Orders detail, Customer detail)

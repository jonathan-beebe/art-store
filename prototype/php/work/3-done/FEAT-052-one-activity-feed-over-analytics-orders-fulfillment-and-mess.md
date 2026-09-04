---
id: FEAT-052
type: feature
status: resolved
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

## Working

### Core

`App\Domain\Seller`: `ActivityKind` (browse | order | shipping | messages),
`FeedIcon` (the heroicon path a row wears), `FeedEvent` (occurredAt, kind,
icon, actor, text, quote?, link?), and `ActivityFeed::merge()` / `filter()`.

`merge()` sorts newest first with PHP's stable sort, so two rows carrying the
same instant come out in the order the reader passed their sources —
browsing, order, shipping, messages. A page reading the same scope twice
reads the same feed. `filter()` narrows what the feed hands back, never what
the sources return.

### Sources

`App\Seller`: `FeedScope` names the story (`forFulfillment`, `forCustomer`),
`ActivityFeedSource` is the one method each source answers, and
`ActivityFeedReader` composes the four.

Two boundaries stop a row appearing twice:

- The analytics store also carries `order.place`, `order.pay`, and
  `order.cancel`. Those are `OrderSource`'s, read from the tables that hold
  the money, where the amounts are. `AnalyticsSource` takes the browsing five.
- `fulfillment_events` carries a `refunded` row and `ledger_entries` carries
  a `refunded` movement. The movement wins — it carries the amount and takes
  the refund's reason as its quote — and `FulfillmentSource` skips that kind.

### Decisions

- Four kinds, the ticket's own list, over the architecture note's five.
  `payment` folds into `order`: the ticket's Outcome puts "payment approved
  or declined" under order, and the prototype's filter bar has the same four
  plus All.
- `FeedIcon::path()` holds the SVG path in the domain, the way
  `AnalyticsEventName::iconPath()` already does, so a row brings its own
  picture and `x-seller.feed` stays a renderer.
- `tests/Pest.php` gained `../app/Seller` so the new namespace's sidecars run
  under `CommerceTestCase` — the smallest diff to a shared file this ticket
  needed.

### Left out

- The `?kind=` FormRequest and its bare 400. The lane's scope names the core,
  the four sources, the reader, the component, and the doc section; no route
  in this ticket carries a query parameter, so the 400 lands with whoever
  wires `?kind=` — FEAT-053 on the order page, FEAT-054 on the customer page.
  `ActivityFeed::filter(?ActivityKind)` is what they will call.
- No page adopts the feed beyond the component's rendering test.
- `docs/seller-portal.md` holds the one section this ticket owes; MAINT-008
  grows the rest.

### Found and left for MAINT-008

`docs/data-model.md` is hand-maintained and now omits FEAT-051's four tables
(`fulfillment_flows`, `fulfillment_flow_steps`, `fulfillment_events`) and
`listings.fulfillment_flow_id`. Four lanes are adding tables at once — the
store profile's seven among them — so one pass on that file at integration
beats four conflicting ones. `docs/alignment.md` §1's prefix table owes the
same three prefixes.

### Found and fixed while testing

`AnalyticsSource` let a `checkout.open` through when its `data.listing_ids`
named none of this seller's pieces — another seller's checkout on the wrong
feed. A row that names none of the seller's listings is now left out
whatever its subject.

### Gate

`make precommit` green: 4232 passed, 33792 assertions.

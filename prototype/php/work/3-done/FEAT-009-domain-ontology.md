---
id: FEAT-009
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-009: Domain ontology doc

## Problem
`docs/` captures flows (sequence diagrams), states, and the table shape, but no doc answers "what are the entities in this product, who or what is each one, why does it exist, and how does it relate to the others". A reviewer meeting `fulfillment`, `ledger_entry`, `customer_merge`, or `magic_link` for the first time has to infer their purpose from migrations and actions.

## Goal
A reviewer can learn the product's vocabulary and the reason each concept exists from one document.

## Outcome
- `docs/ontology.md` lists every entity in the system (people/roles: seller, customer, anonymous visitor, the platform; things: listing, listing event, favorite, cart, cart item, order, order item, payment, fulfillment, ledger entry, payout, notification, magic link, customer merge; and value concepts: money, fee, payout period, card decision).
- For each entity: who/what it is (one sentence), why it is here (the product need it serves), its lifecycle or states if it has one, and its relationships to the other entities (owns / belongs to / produces / settles ...), naming the code that defines it (model, domain class, or enum).
- One Mermaid diagram shows the entities and their relationships at the concept level (not the table level — `docs/data-model.md` already has the ER diagram), with ≤ 12 boxes, grouped by role (seller side, customer side, money).
- Vocabulary is consistent with the code and `docs/architecture.md`; where the product uses a word differently from the code (e.g. "order" on the seller side means a fulfillment), the doc says so.
- `docs/README.md` links the new doc.

## Why it matters
The user asked for it: a list of entities, who they are, why they are here, how they relate.

## Discovery notes
Derive from `src/app/Models/**`, `src/app/Domain/**`, the migrations, and `docs/architecture.md`. Follow the `diagramming` skill for the one diagram. Mermaid reserved words (`to`, `in`, `links`, etc.) break as labels/aliases; validate with `docker run --rm -v "$PWD":/data -v "$PWD/tmp":/tmp minlag/mermaid-cli -i /data/x.mmd -o /data/x.svg` from a scratch directory.

## Working

- 26 entity sections, in the order the ticket lists them plus a Decisions
  group for the four enums (Card decision, Listing status, Order status,
  Fulfillment status) — those are documented under their owning entity's
  Lifecycle and get a short pointer-only section here rather than a repeat.
- Diagram: 10 boxes, three subgraphs (`sellerSide`, `customerSide`,
  `moneySide`), 12 labeled edges. Node aliases avoid Mermaid reserved words
  (`seller`, `listing`, `customer`, `cart`, `order`, `payment`,
  `fulfillment`, `ledger`, `payout`, `platform` — none collide). Validated by
  rendering to SVG with `minlag/mermaid-cli` from a scratch dir; renders
  clean.
- Entities in code that the four existing docs (`architecture.md`,
  `data-model.md`, `orders.md`, `escrow.md`, `identity.md`) never name
  directly: `Purchaser`, `ShippingAddress`, `OrderPayment`, `CheckoutPurchaser`
  (the verified-vs-guest purchaser construction), `ListingAvailability`,
  `ListingStock`, `ListingDraft`, `ListingSlug`, `CartLine`, `CartQuantity`,
  `CartTotals`, `CustomerIdentityAction`, `CustomerIdentityPlan`,
  `FavoriteChange`, `NotificationMessage`, `StatusLabel` (two copies, `Shop`
  and `Reports`), and the whole `app/Domain/Reports/**` tree
  (`ActivityTimeline`, `DailyActivity`, `ListingStatusTally`,
  `ListingStatusCount`, `PayoutSummary`) — these back the seller dashboard
  and activity views but are reporting projections, not new domain
  entities, so `ontology.md` does not give them their own sections.
- Naming inconsistency: the seller portal's URL and controller are named
  `orders` (`seller.orders.*`, `App\Http\Controllers\Seller\OrderController`)
  but the model they bind and render is `Fulfillment`
  (`Route::get('orders/{fulfillment}', ...)`). Documented as the lead
  Vocabulary note and cross-referenced from the Fulfillment section.
- No other product/code vocabulary mismatch found — "sold", "available",
  "verified customer", "guest checkout", and "seller net" all match their
  code definitions directly; called out in Vocabulary notes anyway since a
  reviewer meeting them cold would still have to check.

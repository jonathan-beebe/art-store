---
id: IMPRV-033
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-033: The docs say the seller portal once, and the deferrals become tickets

## Problem
The audit (`__local__/design/seller-portal/AUDIT.md` §5, §6) found `docs/alignment.md` §8 naming seven of eleven shipped tickets, the fulfillment-flow contract written in three files, `docs/seller-portal.md` at 1,072 lines with about 300 lines of third copies and seven stale claims, `data-model.md`'s older blocks missing messaging-v2 and fulfillment columns, `ontology.md` listing four of nine analytics events, and ten follow-ups recorded only inside ticket Working sections.

## Goal
A reader finds each fact about the seller portal in one place, and every deferred item is a ticket someone can pick up.

## Outcome
- `docs/alignment.md` §8 names every shipped ticket; §4.5 alone carries the flow diagram, lane table, writers, and vocabulary consequence; `orders.md` keeps the PHP realization and links §4.5; `seller-portal.md` links both and restates neither.
- `docs/seller-portal.md` is under 700 lines, reads in the nav's order, names the flow editor and the label page, has no claim the code contradicts (audit §5 lists seven), and keeps the sections the audit names as earning their place.
- `data-model.md` matches the migrations for `conversations`, `messages`, `fulfillments`, `listings`, and lists every table whose model has `idPrefix()`; `ontology.md` lists all nine analytics events.
- Every row of audit §6 that has a definite outcome is a ticket in `work/1-inbox` (FEAT or IMPRV as fits) with a Problem, Goal, and Outcome; rows needing a product decision are listed in `__local__/design/seller-portal/DECISIONS.md` rather than filed.
- `work/journal.md` records each new ticket.

## Why it matters
The reconciliation log is how node and rails learn what they owe; a doc that says one thing three times drifts three ways; a deferral inside a Working section is a deferral nobody sees.

## Discovery notes
- `__local__/design/seller-portal/AUDIT.md` §5 and §6.
- Ticket ids come from the journal counters (`/work-write`); `DECISIONS.md` already lists which §6 rows need input.

## Related work
- MAINT-008 (the contract sweep this corrects)
- FEAT-051..061

## Working

### `docs/alignment.md`

§8's last entry (2026-09-03, "seller portal") named seven of the eleven
shipped tickets and omitted FEAT-053, FEAT-054, FEAT-055, FEAT-059, and
MAINT-008 itself. Rewrote its closing sentence to name all eleven plus
MAINT-008. §4.5, §4.4, and §1's prefix table were already current —
MAINT-008 had landed them — so no change was needed there.

### `orders.md`

"The fulfillment event log and the seller's flow" restated §4.5's ER
diagram, lane table, and closed-vocabulary sentence verbatim. Cut both,
replaced with a one-line pointer to §4.5, and kept everything §4.5 does
not carry: the class names (`AppendFulfillmentEvent`, `CompleteFlowStep`,
`SaveFulfillmentFlow`, `FulfillmentProgress`, `FulfillmentLane`), the
sequence diagram, and the PHP-specific mechanics (the partial unique
index, the unique-null behavior, `step_label`'s copy-at-completion). 392
→ 368 lines.

### `seller-portal.md`

Reordered to the nav's order (Dashboard, Store profile with the public
page nested under it as `###`, Listings, Orders, Activity feed, Customers,
Messages, Earnings, Support), plus a closing Data section. Activity feed
stays between Orders and Customers, its place in the merged branch and
the placement the audit's kept-section line numbers (`:479-506`) name.

Cut ~370 lines: the `## Data` section's own ER diagram and full column
list (it now points to `data-model.md`, which carries them once); Store
profile's "The tables" ER diagram (kept the relationships as prose, one
`data-model.md` link); the `### Layers` sections' restated adapter prose
(the classes are still named, the paragraph explaining what each one
does — already in its own docblock — is gone); three flowcharts that
illustrated what the surrounding prose or a table already said
(Dashboard's controller graph, Listings' view-routing graph, Support's
and Earnings' layer graphs); and general prose tightening throughout.
Kept intact, per the audit's "earns its place" list: Activity feed's
"Which source owns which row" table and the two de-duplication bullets
under it; Orders' "Lanes are a query parameter"; Public page's
"Resolving an address"; Dashboard's "What the dashboard costs"; Store
profile's "What the store does not write"; and Listings' "Sold and
revenue are all-time" paragraph.

Corrected the seven stale claims audit §5 lists, each verified against
the code before and after:
1. `Fulfillment::state()` does not exist → `App\Seller\OrderDetail::state()`.
2. All step completions redirect to the order page → only a non-`print_label`
   step does; a `print_label` step redirects to the label page
   (`FlowStepController::__invoke()`).
3. `x-seller.bar-strip` → `x-bar-strip` (the seller-only component was
   removed in FEAT-056's review pass).
4. A `<noscript>` fallback for the sort control → no such element exists;
   replaced with the actual `data-sort-form`/`data-sort-select`/
   `data-sort-submit` hooks and the always-rendered Sort button.
5. Both the workspace and the dialog carry `hidden 2xl:flex` → the
   workspace is `hidden … 2xl:block` with `inert`; the dialog alone is
   `hidden … 2xl:flex` (`detail-overlay.blade.php`).
6. The "needs your attention" empty-sentence list named three of the
   panel's four groups → added the payout group's ("Nothing has settled
   yet.", `AttentionQueue::build()`).
7. The store's tagline (80) and story-body (4,000) limits were presented
   as column facts in the ER diagram → both columns are `text`/`string`
   with no DB-level length; reworded as the validation ceilings they are
   (`StoreProfile::MAX_TAGLINE_LENGTH`, `StoreSection::MAX_BODY_LENGTH`).

Named the flow editor (`GET/PUT /seller/orders/flow`) and the label page
(`GET /seller/orders/{fulfillment}/label`) in "Actions the state allows"
— the doc previously omitted both, per audit §5.

Every class name and route left in the doc was grep-verified against
`prototype/php/src`. Four names are forward-looking, disclosed in one
note under the title rather than scattered per mention: `SellerQueryRequest`
(IMPRV-032's shared base for the five `*QueryRequest` classes the doc
names individually today), `NavLink` (IMPRV-032's replacement for
`ViewLink`/`SegmentLink`/`FeedKindLink`/`LaneTab`, which the doc still
names where they appear), `Fulfillment::live()`/`counted()` (IMPRV-031's
consolidation of the paid/live rule the doc states inline in three places
— Listings' "Sold and revenue are all-time", Customers' derivation
paragraph, Dashboard's "Orders and earnings" paragraph — left as prose
rather than renamed, since neither method exists yet), and
`App\Seller\Store` (IMPRV-032's rename of `App\Support\Store`, which the
doc still uses since that is the code's current namespace). None of the
four is asserted to exist; the note says they don't yet.

1072 → 699 lines.

### `data-model.md`

Compared the `conversations`, `messages`, `fulfillments`, and `listings`
entity blocks against their migrations directly (`2026_08_23_000200_create_messaging_tables.php`,
`2026_08_22_000208_create_fulfillments_table.php`,
`2026_08_22_000200_create_listings_table.php` plus its two `Schema::table`
alters).

- `conversations` was missing `title`, `order_id`, `resolved_at`,
  `resolved_by_type`, `resolved_by_id` — the messaging-v2 columns. Its
  `subject_key` caveat was worse than stale: it described a "kind +
  participant ids" key built from all four kinds' nullable columns, but
  only the `fulfillment` kind carries one
  (`App\Domain\Messaging\ConversationSubject`) — the other three open
  fresh and carry a `title` instead. Rewrote the caveat and the inline
  example (`listing_question:ssel_…` → `fulfillment:s…:c…:f…`) to match
  `ConversationSubject::subjectKey()`, and added a caveat for the new
  `resolved_*` columns.
- `messages` was missing `reply_to_message_id`. Added the column, a
  `messages ||--o{ messages : "replied to by"` self-relationship, and a
  caveat.
- `fulfillments` was missing `customer_id`, `shipped_at`, `delivered_at`.
  Added the three columns and a `customers ||--o{ fulfillments : receives`
  relationship line.
- `listings` was missing `category_id`, `description`, `dimensions`, and
  its `quantity` note said "default 1" without saying the column is
  nullable for a made-to-order piece. Added all three columns (`category_id`
  drawn without a relationship line, with a caveat pointing at
  `item-configurator.md`) and corrected the `quantity` note.

Separately: the prefix table (`## Identifiers`) listed 34 of the 46
prefixes a model's `idPrefix()` actually returns — the sixteen item
configurator tables (`categories` through `description_sections`) were
absent, matching the root `docs/alignment.md` §1 table but not this one.
Added all sixteen with a note that the diagram below omits their columns
and relationships by design, pointing to `item-configurator.md`.

### `ontology.md`

The "Listing event" entry named four of `AnalyticsEventName`'s nine
cases (`listing.view`, `listing.favorite`, `listing.unfavorite`,
`listing.cart_add`) and its own title claimed the table is about
listings, which stopped being true once `checkout.open`, `order.place`,
`order.pay`, `order.cancel`, and `store.view` joined the vocabulary.
Renamed to "Analytics event", listed all nine names, and widened
"Relates to" from "belongs to one Listing" to the four subject kinds
`AnalyticsEvent` actually constructs (`forListing`/`forStore`/`forOrder`/
`forCart`).

### Tickets filed

Three of audit §6's ten rows have a definite outcome not listed in
`DECISIONS.md` as needing the owner's input — IMPRV-034 (`sellerBadgeTint()`'s
name), IMPRV-035 (the dashboard's "needs work" group and
`publishIssues()`'s cost), IMPRV-036 (the listings overlay's
viewport-behavior test gap, split off FEAT-056's row — its `range`-control
half rides with FEAT-054's, both deferred below). Ids from
`work/journal.md`'s `IMPRV` counter (34 → 37); a `defined` line logged for
each.

### Deferred to `DECISIONS.md`

The other seven §6 rows all resolve to a `DECISIONS.md` "Needs your
decision" item and were not filed:

| §6 row | `DECISIONS.md` item |
| --- | --- |
| FEAT-051 (no listing-level flow picker) | 10 |
| FEAT-054 (no `range` control; no pagination) | 3, 4 |
| FEAT-056 (no `range` control in the listings header) | 3 |
| FEAT-057 (store writes emit no event, take no limiter) | 11 |
| FEAT-058 (the `store` analytics subject has no admin page) | 12 |
| FEAT-059 (the rail scrolls with the transcript) | 14 (waits on decision 1) |
| FEAT-060 (net-per-period bars use a hand-rolled percentage idiom) | 15 |
| FEAT-061 (the "Yes" half of "did this answer it?" has no tracking) | 13 |
| owner walk (store picture row overlap; earnings "new" at zero) | 5 |

### Gate

Docs and work files only; the commit hook does not run `make precommit`
for this branch. Verified independently: every class name and route left
in `seller-portal.md`, `orders.md`, and `data-model.md` greps to a real
file in `prototype/php/src`; both files' Mermaid fences balance
(`grep -c '```'` even in each).

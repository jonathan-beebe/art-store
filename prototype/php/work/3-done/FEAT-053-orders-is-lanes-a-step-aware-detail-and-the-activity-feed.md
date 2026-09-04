---
id: FEAT-053
type: feature
status: resolved
created: 2026-09-03
---

# FEAT-053: Orders is lanes, a step-aware detail, and the activity feed

## Problem
The seller orders tool (`resources/views/seller/orders/index.blade.php`, `show.blade.php`, `App\Http\Controllers\Seller\OrderController`) is one list of every fulfillment newest first, with a "need action" count, and a detail of a `dl`, an items list, a shipment form, and a decline form. Nothing separates the three questions a seller asks — what must go out, what is on its way, what is finished — and the detail says nothing about the buyer beyond an email.

## Goal
A seller opens Orders and sees what to ship, what is in flight, and what is done, and each order tells the whole story of the buyer, the goods, the money, the steps, and the parcel.

## Outcome
- The list pane has lanes as underline tabs: To ship (count), In progress (count), Done, All. To ship sorts oldest first; the others newest first. A row shows the buyer, the item line and subtotal, the status badge and date, and, when there is one, a one-line note: the buyer's latest unanswered message on that order's thread, or "Label printed" / the latest completed step.
- The detail header carries the order id and placed date, the buyer's name with the status badge, a state line ("Placed 2 days ago · ship by Sep 5", "In transit with Owl Post since Sep 1", "Delivered Aug 28 · $612.00 released"), and the actions the state allows: Message buyer always; Decline and Mark shipped while awaiting shipment.
- Three cards: Customer (name, email, orders and spend with this seller, since, View customer link), Ships to, Payment (card ending, buyer paid, platform fee, your take, escrow state).
- Items list with the configured options and line totals; each item links to its listing.
- A Steps panel renders the fulfillment's flow: completed steps with who and when, the next step as the one live button, later steps waiting. The label step's button prints the label and records the carrier and tracking. Shipment shows carrier, tracking, shipped, delivered as read-only once shipped.
- The activity feed (FEAT-052) closes the page with a kind filter.
- Every lane, filter, and sort is a query parameter; unknown values answer 400. Below `lg` the detail is its own screen with a back link, as today. `make precommit` green; `make check` green before the PR.

## Why it matters
The brief calls Orders "a place of focus for getting business done". Lanes turn one list into three questions with answers; the step panel makes the seller's own flow the thing they act on; the cards and the feed put the buyer, the money, and the history on one screen so no order needs a second tab.

## Discovery notes
- Lanes come from `FulfillmentLane` (FEAT-051) — a grouped query over status plus "has a completed step" gives the counts in one round trip; the fulfillment cells component (`x-seller.fulfillment-cells`) already renders the row idiom through `x-pane-row`.
- The note: latest message on the fulfillment's conversation where `sender_type = customer` and `read_at` is null, else the latest `step_completed` event's label.
- "Ship by" is placed_at + 3 days, a display rule, not a stored value.
- Customer numbers on the card can come from the same query FEAT-054's `SellerCustomers` will own; if 054 has not merged, compute them in the controller's query class and let 054 replace it.
- The Steps panel is a Blade component (`x-seller.flow-steps`) fed by `FulfillmentProgress`; each button is a POST to the step-completion route from FEAT-051.
- Keep the mobile sticky action bar the current show page has.

## Related work
- FEAT-051, FEAT-052
- IMPRV-029 (list panes adopt the stacked-list row)
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Orders)

## Working

### What landed

- `App\Domain\Fulfillment\LaneFilter` — the `?lane=` vocabulary: the three
  `FulfillmentLane` piles plus `all`, carrying each tab's label, whether it
  wears a count, which end of the queue it reads from, and what it is empty
  of. `FulfillmentLane::forStarted()` reads the lane off status and "has a
  completed step" alone, and `of()` delegates to it, so the grouped count
  and a loaded flow read one rule.
- `App\Domain\Fulfillment\ParcelState` — the state line, one shape per
  status, built from facts and the caller's clock.
  `LedgerEntryType::escrowState()` says where the money stands.
- `Fulfillment`: `inLane` and `countedByLane` scopes (one grouped read over
  status and an `exists` on `step_completed`), `state()`, `escrowState()`,
  `itemLabel()`, `latestStepCompletion()`. `OrderSource` now reads the same
  `itemLabel()` its own copy used to build.
- `App\Seller\FulfillmentLanes` — tabs and pane out as readonly `LaneTab`,
  `OrderRow`, `OrderPane`. The row note is one query for unread customer
  messages and one for step completions across the whole window.
- `App\Seller\CustomerOnOrder` → `CustomerFacts`; `App\Seller\FeedFilter` →
  `FeedKindLink` for the `?kind=` segmented control.
- `App\Http\Requests\Seller\OrdersQueryRequest` owns `lane` and `kind` for
  both routes and answers a bare 400 on an unknown value.
- Views: lanes on both panes (`x-seller.lane-tabs`), rows rebuilt on
  `OrderRow` (`x-seller.fulfillment-cells`), and a detail with the state
  line, three cards, items linked to their listings, the flow's steps, the
  shipment panel, and the feed under its filter. The mobile sticky action
  bar stayed and gained Message.
- `docs/seller-portal.md` gained an Orders section between Listings and
  Activity feed.
- `routes/seller.php` is untouched: the form request is injected into the
  controller the routes already name.

### Decisions

- The index opens on To ship. A detail reached by a link that named no lane
  opens on the lane its own parcel sits in, so the row is always in the pane
  beside it; every row link and the back link carry the lane.
- Lane counts sit on To ship and In progress only. Done and All are
  archives, and the "N need action" caption they replace is gone.
- Rows order by `(created_at, id)`. A fulfillment is created when the order
  is paid and its id is a ULID minted at the same moment, so the pair reads
  the pile the way it filled up without a join to `orders`.
- `CustomerOnOrder` counts what the buyer bought from this seller, leaving
  out declined and refunded parcels. **FEAT-054's `SellerCustomers` replaces
  it** — the card reads three facts and a name/email off `CustomerFacts`,
  so the swap is one call site in `OrderController::show`.

### Left out, and why

- **View customer link on the Customer card.** `seller.customers.show` is
  FEAT-054's route and does not exist yet; naming it would throw. The card
  carries the three facts and waits for the link.
- **The admin fulfillment views still spell their own status-tint match.**
  `FulfillmentStatus::sellerBadgeTint()` is now the seller side's one
  source; `resources/views/admin/fulfillments/show.blade.php` and
  `components/admin/fulfillments-cells.blade.php` still hold copies, and
  converging them is outside this ticket's tool.
- **Ordering by `orders.placed_at`.** It would need a join or a subquery
  order that `ListPaneWindow`'s count would have to survive; `created_at`
  answers the same question for a row that only exists once paid.

### Base drift

The branch is cut from `085fd936`; `php/seller-portal-next` has since gained
lane D and MAINT-008. Two things were adopted from the tip to keep the merge
clean: the tint method is named `sellerBadgeTint()` with the tip's exact body
and test, and the Orders docs section sits before Activity feed so it never
touches the tail Earnings, Support, and Data were appended to.

### Gate

`make precommit` green on every commit. `make check` green before handover.

---
id: FEAT-053
type: feature
status: open
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

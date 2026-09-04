---
id: FEAT-051
type: feature
status: open
created: 2026-09-03
---

# FEAT-051: Fulfillment is an event log with seller-owned flow steps

## Problem
A fulfillment records its life in five status values and three timestamps (`fulfillments.status`, `shipped_at`, `delivered_at`, `app/Models/Fulfillment.php`). Nothing between "paid" and "shipped" exists: a seller who prints a label, packs a parcel, or waits for a glaze to cool has nowhere to say so, the orders list cannot tell a fresh order from one already half done, and every seller ships every kind of good through the same two-button page (`resources/views/seller/orders/show.blade.php`, Mark shipped / Decline).

## Goal
Every seller can describe how their goods get out the door, and every step and transition on a fulfillment is one appended record the rest of the portal can read.

## Outcome
- Every seller owns at least one fulfillment flow: an ordered list of named steps. The seed gives each seller a default flow of two steps, "Label printed" (the step that prints a label) and "Packed". A seller edits their flow from the orders area: add, rename, reorder, remove a step, and choose which step prints a label. An empty flow is allowed.
- A listing may name the flow it ships by; a listing without one ships by the seller's default flow.
- Completing a step on a fulfillment appends one event carrying the step, who did it, and when. Completing the label step also records the carrier and tracking number it printed with and answers a printable label page (a PDF placeholder with the buyer's address).
- Marking shipped, confirming delivery, declining, and refunding keep writing `fulfillments.status` exactly as `docs/alignment.md` §4.1 requires and, in the same transaction, append the matching event. A status that changed without its event, or an event without its status, cannot happen.
- A pure domain function answers, for one fulfillment: which steps are complete, which is next, whether the flow is done, and which lane it belongs to — To ship (awaiting shipment, nothing completed), In progress (awaiting shipment with a completed step, or shipped), Done (delivered, declined, refunded).
- A step cannot be completed twice, out of order, on a fulfillment that is not awaiting shipment, or by a seller who does not own it (404). Unknown values answer 400.
- `docs/orders.md` describes the event log beside the status machine. `make precommit` green; `make check` green before the PR.

## Why it matters
Orders are where a seller's day happens. Today the portal can only say "not shipped" or "shipped"; sellers keep the real state in their heads. A log of steps lets the orders list show what is actually going on, lets the activity feed tell the buyer's story truthfully, and lets a ceramicist and a photographer ship differently without the platform choosing for them. New product categories that fulfill differently become a flow, not a feature.

## Discovery notes
- Schema (from the accepted architecture, `__local__/design/seller-portal/ARCHITECTURE.md` §3): `fulfillment_flows` (`ffl_`; seller_id, name, is_default with one default per seller), `fulfillment_flow_steps` (`ffs_`; flow, seller_id, key unique per flow, label, action `none|print_label`, position unique per flow), `fulfillment_events` (`fev_`; fulfillment_id, seller_id, kind `step_completed|shipped|delivered|declined|refunded`, nullable step id, actor_type/actor_id, nullable carrier and tracking_number, occurred_at), `listings.fulfillment_flow_id` nullable. Prefixed ULIDs minted from the frozen clock like every other table; foreign keys hold the referenced id.
- Suggested pure core under `App\Domain\Fulfillment`: `FulfillmentEventKind`, `FlowStepAction`, `FulfillmentProgress::of(steps, events)`, `FulfillmentLane::of(status, progress)`. `tests/Arch.php` keeps `App\Domain` free of Illuminate and the clock.
- Writers are `App\Actions` (final, invokable): a new `CompleteFlowStep`; the existing `ShipFulfillment`, `ConfirmDelivered`, `DeclineFulfillment`, and the refund action gain the event append inside their transaction. Look at how `IssueRefund` writes a ledger entry in the same transaction for the pattern.
- The seller flow editor can be one resourceful controller pair (`FulfillmentFlowController@edit/update`, steps as nested rows) in the existing seller chrome and form idiom (`x-admin.input`-style fields; see `resources/views/seller/support/create.blade.php` for the field classes). Reordering without JavaScript: position inputs or up/down forms both fit the codebase.
- The label page can render the shipping address in a print stylesheet; a real PDF library is out of scope.
- Seeder: `WizardingSellerSeeder`/`SellerSeeder` give each seller the default flow; `OrderHistorySeeder`'s shipped and delivered fulfillments get their matching events so the log is complete from day one. Harry Potter names only.
- Pest sidecars beside each class; datasets for the lane table; HTTP tests for the editor and the step completion, including the 400/404 paths.

## Related work
- FEAT-004 (seller portal for listings, activity, fulfillment, and earnings)
- docs/alignment.md §4 (transaction lifecycle) — the status machine stays the contract
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Orders lanes, order detail)

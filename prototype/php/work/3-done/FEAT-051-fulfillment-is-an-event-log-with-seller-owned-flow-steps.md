---
id: FEAT-051
type: feature
status: resolved
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

## Working

### Schema

Four migrations (`2026_09_04_000100..000103`): `fulfillment_flows` (`ffl_`),
`fulfillment_flow_steps` (`ffs_`, unique on `(flow, key)` and
`(flow, position)`), `fulfillment_events` (`fev_`, unique on
`(fulfillment_id, fulfillment_flow_step_id)`), and a nullable
`listings.fulfillment_flow_id`.

Two decisions past the accepted ERD:

- `fulfillment_events.step_label` — the step's words copied onto the row.
  The alternative was `restrictOnDelete`, which would stop a seller tidying
  a step they had ever completed. With the copy the foreign key nulls out
  and the log still reads. It also spares FEAT-052's shipping source a join.
- `fulfillment_events.actor_type` casts to `App\Domain\Auth\ActorType`
  (seller | customer | admin). The ERD names `system` as a fourth value; no
  writer produces one, so the enum was left as it is.

The unique index on `(fulfillment_id, fulfillment_flow_step_id)` is what
makes "a step cannot be completed twice" a database fact. Transition rows
name no step, and a unique index counts each null as its own value, so they
sit outside it.

### Core

`App\Domain\Fulfillment`: `FlowStepAction`, `FulfillmentEventKind`,
`FlowStep`, `FlowStepDraft`, `DefaultFlow`, `NewFulfillmentEvent`,
`FulfillmentProgress`, `FulfillmentLane`. `FulfillmentProgress::of()` takes
the flow as it stands now plus the completed step ids, so an event naming a
removed step leaves the remaining steps where they were.

`NewFulfillmentEvent` is the pairing guard: a transition names no step, a
label step carries a carrier and a tracking number, and every other step
carries neither. Those three rules are unit tested with no database.

### Writers

`AppendFulfillmentEvent` is the one writer of the table. `MarkShipped`,
`ConfirmDelivered`, `DeclineFulfillment`, and `RefundFulfillment` each call
it inside the transaction that writes `fulfillments.status`.
`CompleteFlowStep` takes the fulfillment for update, refuses a status past
`awaiting_shipment` and a step that is not the one in front, then appends.
`SaveFulfillmentFlow` rewrites a seller's default flow from their form,
keeping rows the form names by id and parking surviving positions on
negatives while it refills from zero — the `ReorderDescriptionSection`
sentinel idiom, because SQLite judges `(flow, position)` row by row.

### Surface

`GET/PUT /seller/orders/flow` (declared before `orders/{fulfillment}` so the
path is not read as an id), `POST /seller/orders/{fulfillment}/steps/{step}`,
`GET /seller/orders/{fulfillment}/label`. The order page grows a flow-steps
section above Shipment, rendered by `x-seller.flow-steps` so FEAT-053 can
place the same component in the orders workspace.

Reordering has no JavaScript: each row carries a number, and the request
sorts the drafts by it. A ticked Remove drops a row; a blank label drops the
trailing "add a step" row.

### Left out, and why

- No `Story` line for step completion or the flow editor. `docs/alignment.md`
  §2.3 closes the event vocabulary: "a write with no event above stays
  silent". `fulfillment.step` and a flow-editor event are candidates for
  MAINT-008 to add to the contract; until then the appended row is the
  record.
- No alignment.md edit. ARCHITECTURE § "Contract changes" gives the prefixes
  and the §4 additions to MAINT-008.
- No bare 400. `docs/alignment.md` §5's 400 is for query parameters, and
  FEAT-051 introduces none; the flow form's `action` select is a closed
  vocabulary refused by `Rule::enum` with the codebase's form idiom (errors
  flashed back), and a step or fulfillment that is not the seller's is 404.
- No listing-level flow picker. `listings.fulfillment_flow_id` exists and
  `Fulfillment::flowInEffect()` reads it; the editor edits the seller's
  default flow, which is the route the architecture names.
- `Fulfillment::lane()` and `progress()` read per fulfillment. A lane count
  over a list is FEAT-053's, and will want one grouped query.

### Guardrails the change had to teach

- `OwnershipRoutesTest` needed an owned-by-another-seller value for the new
  `{step}` parameter; without it the route table's new row would have gone
  unguarded.
- `DatabaseSeederTest` counts the seeders; the count moved from 11 to 12.

### Gate

`make precommit` green: 4182 passed, 33628 assertions.

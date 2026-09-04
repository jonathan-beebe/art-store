---
id: FEAT-064
type: feature
status: open
created: 2026-09-04
---

# FEAT-064: A seller can keep more than one fulfillment flow and pick one per listing

## Problem
A seller owns exactly one fulfillment flow: the flow editor (`GET/PUT /seller/orders/flow`, FEAT-051) edits the default and cannot add another, and although `listings.fulfillment_flow_id` exists, no screen sets it. A ceramicist who ships mugs in a box and sculptures on a pallet has one list of steps for both.

## Goal
A seller whose goods ship differently can describe each way once and say which way each listing ships.

## Outcome
- The flow editor lists the seller's flows, adds a new one (name and steps), edits any of them, marks one as the default, and removes a flow no listing names; removing the default is refused while another flow does not hold the role.
- The listing configurator's basics page shows a "Ships by" picker only when the seller has more than one flow; it lists the flows with the default marked and saves `listings.fulfillment_flow_id` (null means the seller's default). A seller with one flow sees no picker.
- An order for a listing that names a flow shows that flow's steps; an order whose listing names none shows the default; a parcel already in progress keeps the flow it started under (its completed events name their steps).
- A flow named by a listing cannot be removed; the editor says which listings name it. Cross-seller flows answer 404; unknown values 400.
- `docs/seller-portal.md` § Orders says how a parcel's flow is chosen. `make precommit` green; `make check` green before the PR.

## Why it matters
The event log was built so goods that fulfill differently could each have their own steps; without a second flow and a picker, every seller has one.

## Discovery notes
- Owner decision (`__local__/design/seller-portal/DECISIONS.md` decision 10): per listing; one flow means no picker; a listing type that maps to a flow is a later idea, so leave `categories` alone.
- `Fulfillment::flowInEffect()` already resolves listing flow → seller default; `SaveFulfillmentFlow` and `FulfillmentFlowController@edit/update` are the editor to grow into a resource (`index`, `create`, `store`, `edit`, `update`, `destroy`).
- The one-default-per-seller partial unique index (`fulfillment_flows`) means "make default" is one transaction: clear the old, set the new.
- The basics page (`ListingBasicsController`, `seller/listings/basics/edit.blade.php`) is where the picker fits; `ListingRequest` gains the nullable id with an ownership rule.

## Related work
- FEAT-051 (flows, steps, event log), FEAT-053 (orders detail), FEAT-025..029 (configurator)

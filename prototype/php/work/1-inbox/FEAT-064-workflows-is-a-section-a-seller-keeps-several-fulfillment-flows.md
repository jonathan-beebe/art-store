---
id: FEAT-064
type: feature
status: open
created: 2026-09-04
---

# FEAT-064: Workflows is a section: a seller keeps several fulfillment flows and picks one per listing

## Problem
A seller owns exactly one fulfillment flow: the flow editor (`GET/PUT /seller/orders/flow`, FEAT-051) edits the default and cannot add another, and although `listings.fulfillment_flow_id` exists, no screen sets it. A ceramicist who ships mugs in a box and sculptures on a pallet has one list of steps for both.

## Goal
A seller whose goods ship differently can describe each way once and say which way each listing ships.

## Outcome
- The seller nav gains **Workflows** between Orders and Customers. It opens `/seller/workflows`: the seller's fulfillment flows, the default marked, each with its step count and the listings that name it.
- From there a seller adds a workflow (name and steps), edits any of them, marks one as the default, and removes one no listing names; removing the default is refused while no other holds the role. Cross-seller ids answer 404; unknown values 400.
- The code keeps the domain's names (`FulfillmentFlow`, `fulfillment_flows`, `fulfillment_flow_steps`, `FulfillmentFlowController`); every seller-facing word (nav, headings, buttons, hints, route segment) says workflow. The old `/seller/orders/flow` route redirects to the default workflow's edit page.
- The listing configurator's basics page shows a "Workflow" picker only when the seller has more than one; it lists them with the default marked and saves `listings.fulfillment_flow_id` (null means the seller's default). A seller with one workflow sees no picker.
- An order shows the workflow its listing names, else the default; a parcel already in progress keeps the workflow it started under (its completed events name their steps). The order page's steps panel links to the workflow it runs under.
- `docs/seller-portal.md` gains a Workflows section that states the naming rule (code: fulfillment flow; UI: workflow) and how a parcel's workflow is chosen. `make precommit` green; `make check` green before the PR.

## Why it matters
The event log was built so goods that fulfill differently could each have their own steps; without a second flow and a picker, every seller has one.

## Discovery notes
- Owner decisions (`__local__/design/seller-portal/DECISIONS.md` decisions 10 and 16): per listing; one workflow means no picker; a listing type that maps to a workflow is a later idea, so leave `categories` alone; the concept keeps its fulfillment name in code and is called a workflow in the UI, with its own nav tab.
- `Fulfillment::flowInEffect()` already resolves listing flow → seller default; `SaveFulfillmentFlow` and `FulfillmentFlowController@edit/update` are the editor to grow into a resource (`index`, `create`, `store`, `edit`, `update`, `destroy`).
- The one-default-per-seller partial unique index (`fulfillment_flows`) means "make default" is one transaction: clear the old, set the new.
- The basics page (`ListingBasicsController`, `seller/listings/basics/edit.blade.php`) is where the picker fits; `ListingRequest` gains the nullable id with an ownership rule.

## Related work
- FEAT-051 (flows, steps, event log), FEAT-053 (orders detail), FEAT-025..029 (configurator)

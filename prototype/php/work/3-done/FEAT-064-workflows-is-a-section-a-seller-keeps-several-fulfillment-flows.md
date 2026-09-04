---
id: FEAT-064
type: feature
status: resolved
created: 2026-09-04
---

# FEAT-064: Workflows is a section: a seller keeps several fulfillment flows and picks one per listing

## Problem
A seller owns exactly one fulfillment flow: the flow editor (`GET/PUT /seller/orders/flow`, FEAT-051) edits the default and cannot add another, and although `listings.fulfillment_flow_id` exists, no screen sets it. A ceramicist who ships mugs in a box and sculptures on a pallet has one list of steps for both.

## Goal
A seller whose goods ship differently can describe each way once and say which way each listing ships.

## Outcome
- The seller nav gains **Workflows** between Orders and Customers. It opens `/seller/workflows`: the seller's fulfillment flows, the default marked, each with its step count and the listings that name it.
- From there a seller adds a workflow (name and steps), edits any of them, marks one as the default, and removes one no listing names; removing the default is always refused — a seller makes another workflow the default first. Cross-seller ids answer 404; unknown values 400.
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

## Working

- `SaveFulfillmentFlow` now takes the `FulfillmentFlow` it writes instead of
  resolving a seller's default; `CreateFulfillmentFlow` makes a new one
  (defaulting a seller's first flow, delegating to `SaveFulfillmentFlow` for
  the name/steps write); `MakeFulfillmentFlowDefault` hands the role to one
  flow inside one transaction, excluding the target row from the "clear the
  old default" bulk update so an already-default flow isn't cleared and then
  skipped on the write-back (Eloquent's dirty-check would otherwise no-op
  the second write); `DeleteFulfillmentFlow` refuses a flow that holds the
  default role or that a listing names. `FulfillmentFlowPolicy` mirrors
  `FulfillmentPolicy`/`ListingPolicy`'s ownership-only, `denyAsNotFound`
  idiom.
- `FulfillmentFlowController` is now a full resource
  (`Route::resource('workflows', ...)->except('show')`) plus
  `MakeFulfillmentFlowDefaultController` (`POST .../default`) and
  `LegacyFlowRedirectController` (`GET /seller/orders/flow`, 301). Renamed
  `UpdateFulfillmentFlowRequest` to `FulfillmentFlowRequest`, used for both
  store and update; its step-id ownership check now reads the route-bound
  `workflow` instead of always the seller's default, and its `authorize()`
  follows the `ListingRequest` idiom (`Gate::inspect('update', $flow)` when
  a flow is bound, `allow()` on create).
- **Working notes / ambiguous-outcome reading**: the ticket's "the old route
  redirects to the default workflow's edit page" assumes a default always
  exists. A seller who has never saved a flow (a real, reachable state —
  nothing mints a flow on signup in this prototype; `FulfillmentFlowSeeder`
  is a data seeder, not a registration hook) has zero rows. Simplest
  reading taken: the redirect goes to the default's edit page when one
  exists, and to the workflows index (which carries its own "add one"
  empty state) when it does not. `GET /seller/workflows` itself never
  auto-creates a row — it reads real rows only, the same way the old GET
  editor read but rendered nothing.
- Views: `resources/views/seller/workflows/{index,create,edit,_form}.blade.php`.
  `index` is a plain table (customers-index idiom), not a list/detail pane —
  a seller's flow count is small and the ticket didn't ask for a pane.
  `_form` is the old `orders/flow/edit.blade.php` step editor, parameterized
  by `action`/`method`/`name`/`steps`/`submitLabel` and included by both
  `create` and `edit`.
- Nav: one entry added to `components/layouts/seller.blade.php`'s
  `$navLinks` array, between Orders and Customers (heroicon `list-bullet`).
- Order page: `OrderFacts` gained `flowId` (nullable — a seller with no flow
  at all still renders a page); `OrderDetail::facts()` sets it from
  `flowInEffect()->id`. The steps panel's "Workflow settings" link and
  `x-seller.flow-steps`'s empty-state "Add some" link both point at the
  specific workflow (or the index, when the parcel's seller carries none).
  `app/Models/Fulfillment.php` untouched — IMPRV-040 owns moving
  `flowInEffect`/`flowSteps`/`progress` off it.
- Listing picker: `ListingDraft` gained `fulfillmentFlowId` (default null,
  last positional param); `ListingRequest`'s `updateRules()` validates
  ownership (`Rule::exists('fulfillment_flows','id')->where('seller_id', …)`)
  and `prepareForValidation()` blanks the picker's empty-string "seller
  default" option to null before that rule runs. `toDraft()` reads the
  field only `$this->has(...)` — absent (picker not rendered, one flow)
  keeps the listing's own value, present-and-blank clears it to null,
  mirroring how price/quantity already treat an unrendered field on a
  configured listing. `ListingBasicsPageData` carries `workflows` (empty
  unless the seller holds more than one, default first) for the picker.
- Tests-first per route/action/policy landed alongside each piece; every
  new controller carries its own `*Test.php` sidecar (`tests/SidecarsTest`
  enforces this per class, so the make-default and legacy-redirect HTTP
  tests live in their own controller's sidecar rather than folded into
  `FulfillmentFlowControllerTest`).
- Nothing scaled back from the ticket's Outcome bullets.
- Gate: `make precommit` (lint + full suite) green — 5283 passed. Ran under
  a shared, CPU-bound host (five lanes), so `git commit` (which runs the
  hook) took several minutes per commit; per the coordinator's guidance,
  `make check` was **not** run — the orchestrator runs one gate on the
  merged branch.

### Review round

Fixed items 2–12 from the merge review: explicit `authorize('update', …)`
in `FulfillmentFlowController::update()`; two `OrderControllerTest` cases
for the flow panel's link (a named workflow, and the index for a seller
with none); the default-deletion rule is strict and stays that way — the
Outcome bullet above now says so plainly, and `DeleteFulfillmentFlowTest`
covers deleting a flow once it has lost the default role to another;
`ListingBasicsControllerTest` asserts the `fulfillment_flow_id` control
itself rather than loose text; `docs/ontology.md`'s Fulfillment flow
lifecycle and `docs/orders.md`'s flow section both name `/seller/workflows`
and the Basics picker; the index's "Make default"/"Remove" buttons carry an
sr-only workflow name; `MakeFulfillmentFlowDefault` returns void, its own
test and `DeleteFulfillmentFlowTest` re-read the row; `ListingRequest::prepareForValidation()`
carries `#[Override]`; `CreateFulfillmentFlow`'s "does this seller have a
flow yet" read is `lockForUpdate()`d inside the transaction; the index
query adds `withCount('listings')` and eager-loads each flow's first three
listings (ordered by title) for the row, folding the rest into "and N
more"; the four flagged contrast-clause comments are rephrased as plain
statements. `make precommit` green.

Item 1 (a parcel keeps the workflow it started under as a snapshot,
`fulfillments.fulfillment_flow_id`) waited on `App\Seller\FulfillmentFlowReader`
landing on `php/fu-model` and a rebase onto `php/seller-portal-next`.

### Item 1

Rebased onto `php/seller-portal-next` (tip `8b565f1b`, carrying fu-model's
`FulfillmentFlowReader` and IMPRV-034's `badgeTint()` rename) — the
`OrderDetail::facts()` conflict took fu-model's `$this->flow->read()` call
and kept the `flowId:` argument name the merged `OrderFacts` already
carried. New migration `2026_09_04_000104_add_fulfillment_flow_id_to_fulfillments_table.php`
(a new migration, not an edit to `create_fulfillments_table`, since it has
to run after `fulfillment_flows` exists): nullable
`fulfillments.fulfillment_flow_id`, `nullOnDelete()` matching the listings
column's idiom. `PlaceOrder::flowIdFor()` stamps it per seller at
placement — the first of that seller's own cart lines that names a flow,
else the seller's default — reusing `Cart::items` already eager-loaded for
the stock lock, so no extra query beyond the one for the seller's default.
Every seeder that places an order (`OrderHistorySeeder` via `PlaceOrder`)
inherits the stamp for free; no seeder edit needed.
`FulfillmentFlowReader::flowInEffect()` reads the snapshot first, live
resolution only for a pre-column row; `Fulfillment::fulfillmentFlow()` is
the new relation, eager-loaded alongside the other flow relations in
`OrderDetail::facts()`, `CompleteFlowStep::assertInFront()`, and the shared
test helper `CommerceTestCase::loadedForFlow()`.

One pre-existing test's premise flipped under the new rule and needed
rewriting rather than just re-running: `CompleteFlowStepTest`'s "ships by
the flow a listing names instead of the seller default" had repointed the
listing's flow **after** placement and expected live resolution to follow
it — that's exactly what the snapshot now refuses to do. Rewritten to set
the listing's flow before placement, which is the case the snapshot is
for.

`FulfillmentFlowReaderTest` gained the two-scenario test the review asked
for: complete a step, then (a) repoint the listing to a different flow, and
(b) separately, make a different flow the seller's default — both assert
the reader still resolves the original flow, the panel's steps still marks
the original step done, and `Fulfillment::lane()` still reads `InProgress`.

Gate: `make precommit` green, 5327 tests.

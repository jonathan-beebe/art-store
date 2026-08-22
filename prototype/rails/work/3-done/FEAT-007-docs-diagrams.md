---
id: FEAT-007
type: feature
status: resolved
created: 
---

# FEAT-007: Docs folder with sequence, flow, state, and ER diagrams

## Problem
Once FEAT-002 through FEAT-006 land, the implemented flows have no diagrams a reviewer can read, and `docs/architecture.md` may have drifted from the code.

## Goal
A reviewer can understand every end-to-end flow from `docs/` without reading code.

## Outcome
- `docs/architecture.md` matches the code (table names, route names, statuses, folder layout).
- `docs/identity.md`: sequence diagrams for seller magic-link sign-in and customer guest verification with merge; flowchart of the `CustomerIdentity` concern.
- `docs/orders.md`: sequence diagram of checkout → finalize → seller notification; state diagrams for `OrderStatus` and `FulfillmentStatus` derived from the enum tests.
- `docs/escrow.md`: ledger flow hold → release → payout, sequence of `payouts:run`, worked numeric example.
- `docs/data-model.md`: ER diagram from `db/schema.rb`.
- `docs/ontology.md`: every entity — who/what it is, why it exists, lifecycle, relations, code pointer — grouped Roles / Catalog / Buying / Money / Identity & messaging / Decisions, one concept-level diagram, vocabulary notes.
- `docs/README.md` indexes the docs. Every diagram is Mermaid, preceded by the question it answers, validated to render.

## Why it matters
Diagrams are how the team reads the flows.

## Discovery notes
Follow the `diagramming` skill. Mermaid reserved words (`to`, `in`, `links`, `end`…) break as bare labels/aliases. Validate each block with `docker run --rm -v "$D":/data -v "$D/tmp":/tmp minlag/mermaid-cli -i /data/x.mmd -o /data/x.svg`. Route names from `bin/rails routes`. The PHP spike's `docs/` is the same product; reuse structure, re-derive content from the Rails code.

## Working

### Drift corrected in `docs/architecture.md`

- **Storefront visibility.** The doc said "only `for_sale` listings appear on
  the storefront"; `Domain::Listings::ListingAvailability::ON_STOREFRONT`
  includes `sold` too — a listing keeps its own page after it sells out, only
  search/browse are `for_sale`-only.
- **Platform fee timing.** The doc said the fee is "taken at `held`"; it is
  computed once in `Orders::PlaceOrder` (order placement) and stored on
  `fulfillments.fee_cents`/`net_cents` — `FinalizeOrder` and
  `ConfirmDelivered` move `fulfillment.net` through escrow without
  recomputing it.
- **Order status diagram missing an edge.** `payment_failed -> cancelled` is
  in `Domain::Orders::OrderStatus::TRANSITIONS` but wasn't drawn.
- **Test runner scope.** `bin/rails test app lib` was stale — FEAT-006 added
  `db` to the Makefile's `test`/`coverage` targets for `db/seeds_test.rb`.
- **Coverage thresholds.** The doc claimed a tiered ≥90% domain / ≥80%
  overall enforcement; `test/test_helper.rb` sets one global
  `minimum_coverage`, currently 80 via `make coverage`. Domain sits near 100%
  in practice, not by a separate enforced floor.
- **Added, not previously stated:** action namespaces are the plural
  directory name (`Carts::`, `Fulfillments::`, …) because `Cart`/`Fulfillment`/
  `Seller` are already AR model class names (Zeitwerk collision); a seller's
  order page binds a `fulfillments.id`; verifying only reaches
  `awaiting_payment` (`Orders::MarkAwaitingPayment`), not `paid`; listing
  validation lives in `Domain::Listings::ListingDraft`, not on the model;
  `ledger_entries.entry_type` / `listing_events.event_type` avoid the AR STI
  `type` column name; `docker/` (entrypoint script) was missing from the
  repository-layout tree.

### Approach

Read every `## Working` section in `work/3-done/` first for decisions that
outrun the original ticket text, then re-derived every name, enum, transition,
route, and table straight from `src/` (domain modules, `schema.rb`, actions,
controllers, `bin/rails routes`) rather than trusting either the architecture
doc or the PHP spike's docs. The PHP `docs/` supplied structure and which
questions each diagram should answer; every diagram's content is new. One
non-obvious Rails departure from the PHP spike: `pending_verification -> paid`
is not a legal `OrderStatus` transition here (PHP allowed it) — a guest order
must pass through `awaiting_payment` first, called out in `orders.md`.

### Verified

- All 17 Mermaid blocks (architecture.md: 4, identity.md: 3, orders.md: 3,
  escrow.md: 2, data-model.md: 1, ontology.md: 1 — README.md carries none)
  render with
  `docker run --rm -v "$D":/data -v "$D/tmp":/tmp minlag/mermaid-cli`, no
  errors. 17/17.
- Cross-checked every `Domain::`, model, and action name quoted in the new
  docs against the actual `src/app/domain/**`, `src/app/actions/**`, and
  `src/app/models/**` files (see file list above) — no invented names.
- Route names checked against `docker compose run --rm app bin/rails routes`
  output, not against the PHP spike's route names.

### Contradictions found in the code (not fixed — out of this ticket's scope)

- None found. `Domain::Orders::OrderStatus::TRANSITIONS` and the enum tests
  agree; `Domain::Escrow::LedgerBalance` and the Makefile-recorded working
  notes agree on the fee/hold/release/payout arithmetic checked against the
  worked example in `docs/escrow.md`.

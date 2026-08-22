---
id: FEAT-007
type: feature
status: open
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

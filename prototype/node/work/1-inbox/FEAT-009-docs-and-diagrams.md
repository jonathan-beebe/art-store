---
id: FEAT-009
type: feature
status: open
created: 2026-08-22
---

# FEAT-009: Docs — architecture drift check, identity, orders, escrow, messaging, admin, data model, ontology

## Problem
`docs/architecture.md` was written before any code existed and will have drifted; the brief asks for a docs folder with sequence diagrams, flow charts, and state machines capturing the product, and the two new areas (admin, messaging) have no feature docs.

## Goal
A reader can understand every flow in the product from `docs/` alone, and every diagram renders and names real code.

## Outcome
- `docs/architecture.md` corrected against the code (layout, routes, names, thresholds).
- `docs/identity.md` (sequence: seller sign-in; guest verification with merge; identity resolution flowchart), `docs/orders.md` (checkout sequence, order and fulfillment state diagrams incl. cancel), `docs/escrow.md` (ledger flow, payout sequence, worked example), `docs/messaging.md` (conversation kinds, ask → answer → FAQ sequence, access rule), `docs/admin.md` (moderation effects, page-view rollup), `docs/data-model.md` (ER diagram from the migrations), `docs/ontology.md` (every entity incl. admin, removal, block, conversation, message, FAQ), `docs/README.md` index.
- `make docs-check` extracts every Mermaid block and renders it with `minlag/mermaid-cli`; all pass.

## Why it matters
The showdown compares three stacks; the docs are how a reader who did not build it judges the design.

## Discovery notes
`prototype/rails/docs/*` is the model — same doc set plus two new ones. Follow the `diagramming` skill: one question per diagram, names match the code, prose frames.
- Render check: `docker run --rm -v "$D":/data -v "$D/tmp":/tmp minlag/mermaid-cli -i /data/x.mmd -o /data/x.svg` (bind-mount `/tmp` to avoid ENOSPC). Bare labels `to`, `in`, `links`, `end` break the parser — quote them.

## Related work
- `prototype/rails/work/3-done/FEAT-007-docs-diagrams.md`

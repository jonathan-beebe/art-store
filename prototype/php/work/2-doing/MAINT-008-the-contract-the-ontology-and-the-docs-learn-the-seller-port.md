---
id: MAINT-008
type: maintenance
status: open
created: 2026-09-03
---

# MAINT-008: The contract, the ontology, and the docs learn the seller portal

## Problem
The seller-portal work adds tables, an analytics event, an event log beside the fulfillment state machine, and two new domain words (a seller's customer is a buyer; a fulfillment flow), none of which `docs/alignment.md`, `docs/ontology.md`, `prototype/php/docs/ontology.md`, or the prototype docs know. Node and Rails will diverge silently.

## Goal
Every shape the seller portal adds is written down once where the three prototypes and their readers look for it.

## Outcome
- `docs/alignment.md` §1 lists the new prefixes (`sto sim sse ssi ssl slk ffl ffs fev`), §2.6 lists `store.view`, §4 describes the fulfillment event log and seller flow steps beside the status machine, §4.4 lists label printing and step completion on the seller surface, and §8 records the PHP-first landing with parity owed by node and rails.
- Both ontology files define Store profile, Store section, Fulfillment flow, Flow step, Fulfillment event, and Activity feed, and the Customer entry says that from a seller's side a customer is a buyer.
- `prototype/php/docs/seller-portal.md` exists and the docs README indexes it: screens, routes and query vocabularies, the feed sources, the store schema rule, the flow and lanes, with Mermaid where a picture is shorter.
- Parity tickets exist in `prototype/node/work/1-inbox` and `prototype/rails/work/1-inbox` naming what each owes.

## Why it matters
The alignment contract is how three prototypes stay one product; a change written in code alone is a change the other two cannot see.

## Discovery notes
- Write after FEAT-051 and FEAT-057 land so the tables are final; the architecture note in `__local__/design/seller-portal/ARCHITECTURE.md` is the source and can be moved, trimmed, into `docs/seller-portal.md`.

## Related work
- FEAT-051, FEAT-057, FEAT-058
- MAINT-006 (the previous alignment sweep)

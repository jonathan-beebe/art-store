---
id: FEAT-023
type: feature
status: open
created: 2026-09-04
---

# FEAT-023: The seller portal grows a store, a fulfillment flow, and a feed

## Problem
`docs/alignment.md` gained nine tables, three prefixes, one analytics event, and a whole subsection on 2026-09-03: §1 lists `sto ssl sim sse ssi slk ffl ffs fev`, §2.6 lists `store.view`, §4.5 describes an append-only fulfillment event log beside §4.1's status machine and the seller's own ordered flow of steps between paid and shipped, and §4.4's seller row names label printing and step completion. Node carries none of it. A seller on the node prototype has no store page, no way to record what they did to a parcel beyond marking it shipped, and no one place to read everything that passed between them and a buyer.

## Goal
A seller on the node prototype presents a store, works a parcel through their own flow, and reads one feed — the same shapes §1, §2.6, §4.4, and §4.5 fix for all three prototypes.

## Outcome
- The nine tables of §1 exist with the prefixes the contract names: `store_profiles` (`sto`), `store_slugs` (`ssl`), `store_images` (`sim`), `store_sections` (`sse`), `store_section_images` (`ssi`), `store_links` (`slk`), `fulfillment_flows` (`ffl`), `fulfillment_flow_steps` (`ffs`), `fulfillment_events` (`fev`), and a listing may name a flow.
- A seller has one store profile carrying identity, address, pictures, and visibility, and everything the page says is a typed, ordered section row. A new kind of store content is a new kind of section, never a wider profile row and never a JSON blob.
- Addresses are history: the current one is on the profile, every address the store has ever answered to is its own row unique across the table, and one retired inside thirty days redirects to the current one.
- A store's public page answers at its current address. A hidden store, an address retired too long ago, and an address no store ever held all answer the same 404. Every listing card and listing page names the seller as a link to their store when it is published.
- A view of a published store page records `store.view` with `subject_type = 'store'` and the profile's `sto_` id, collapsed to one row per (store, customer, UTC hour). A seller previewing their own hidden page records nothing.
- `fulfillments.status` stays §4.1's state machine, and every transition that writes it appends its matching `fulfillment_events` row in the same transaction, so a status that moved without its event cannot commit.
- A seller owns an ordered flow of steps between paid and shipped, one default per seller held by the database. Completing a step appends a `step_completed` row that keeps the step's words, so a step later renamed or removed leaves the log reading as it did. A step is completed only from `awaiting_shipment`, only when it is the step in front, and only by the seller who owns the parcel; a step completed twice is one row.
- The step whose action is `print_label` takes a carrier and a tracking number and answers a printable label page. A seller can add, rename, reorder, and remove steps and choose which one prints the label.
- A seller's parcels sort into the three lanes of §4.5 — To ship, In progress, Done — read from status and completed steps together.
- One activity feed reads browsing, order and money, parcel, and message facts into a single list, newest first, filterable by kind, with no fact told twice.
- `docs/alignment.md` §8 records node's landing, and node's own docs describe what shipped.

## Why it matters
The alignment contract is how three prototypes stay one product. PHP landed these shapes first; until node carries them, a reader cannot put the two side by side, and the next change to the store schema or the event log will be designed against one prototype.

## Discovery notes
- The reference is `prototype/php/docs/seller-portal.md` (the store's six tables, the section rule, addresses as history, the public page, the feed's four sources and which owns which row) and `prototype/php/docs/orders.md` § "The fulfillment event log and the seller's flow" (the two writers, the unique indexes, the lanes). `docs/alignment.md` §4.5 is the contract-level statement; the two php docs are how one stack answered it.
- §2.3's event vocabulary is closed and `fulfillment.step` sits in its reserved list, so a step completion writes the appended row and no log line.
- The lane split needs status and "has a completed step" together; a grouped query over both is what php's desk counts from.
- One default flow per seller is a partial unique index in php (`where is_default = 1`). Node's SQLite takes the same clause.
- Idiom is node's own — knex migrations, the core/adapters split the prototype already uses, EJS views. The contract fixes names, shapes, state machines, and the analytics event; layout and code shape are per stack.

## Related work
- `docs/alignment.md` §1, §2.6, §4.4, §4.5, and the 2026-09-03 §8 entries
- php FEAT-051, FEAT-052, FEAT-056, FEAT-057, FEAT-058, FEAT-060, FEAT-061, MAINT-008
- rails FEAT-022 (the sibling parity ticket)

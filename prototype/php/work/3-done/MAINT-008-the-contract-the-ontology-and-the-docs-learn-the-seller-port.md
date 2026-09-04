---
id: MAINT-008
type: maintenance
status: resolved
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

## Working

Written after lanes A, B, C, and D merged into `php/seller-portal-next`, so
the nine tables were final. Lanes E (FEAT-054, FEAT-059) and A2 (FEAT-053)
were building at the same time and own their own sections of
`docs/seller-portal.md`; every edit here is additive so those merges stay
clean.

### `docs/alignment.md`

- §1 gains `ffl`, `ffs`, `fev`, placed beside `ful`. Lane B had already
  added `sto`, `ssl`, `sim`, `sse`, `ssi`, `slk`, so the set is complete.
- §2.3's "Reserved for a future round" list gains `fulfillment.step`, with
  the reason: the vocabulary is closed, so a step completion writes the
  appended row and no log line.
- §2.6 already listed `store.view` (lane B, FEAT-058). Unchanged.
- §4.1's fulfillment bullets gain a pointer to the log beside the column.
- §4.5 is new: the two tables and the event row, the flow and its steps, the
  two kinds of writer, the unique indexes, and the three lanes. §4.1–§4.4
  keep their numbers — `docs/escrow.md`, `docs/review.md`, the two other
  prototypes' docs, and several done tickets cite them by number, so the new
  subsection goes at the end of §4 rather than renumbering.
- §4.4's seller row gains the flow panel, the `print_label` step with its
  carrier and tracking number and its label page, and the flow editor.
- §8 gains a dated entry recording the PHP-first landing and naming node
  FEAT-023 and rails FEAT-022 as the parity tickets.

### Ontology

- `docs/ontology.md` (root, terse Is/Is-Not): Store profile and Store section
  after Sellers; Fulfillment flow, Flow step, Fulfillment event, and Activity
  feed after Quantity break. The Customer entry already carried the seller's
  meaning; left alone.
- `prototype/php/docs/ontology.md`: a `## Store` section (Store profile,
  Store section) after Catalog, and Fulfillment flow, Flow step, Fulfillment
  event, Activity feed after Fulfillment under Buying. The paragraph under
  the concept diagram now says which of the new concepts sit off it.

### Prototype docs

- `docs/seller-portal.md` gains an intro table naming all seven sections
  with anchors, and a closing `## Data` section: one Mermaid `erDiagram` of
  the nine tables read off the migrations (ARCHITECTURE.md §3's shape,
  corrected — `step_label` on `fulfillment_events`, the second unique
  indexes on `store_section_images` and `store_links`, the indexes lane A
  and lane B added), plus six notes for what a diagram cannot draw.
- `docs/data-model.md`: the prefix table widened and completed with all nine
  new tables, entity blocks for all nine, `listings.fulfillment_flow_id`,
  twelve relationship lines, and seven caveats.
- `docs/README.md` carried three `seller-portal.md` rows — one per lane that
  added a section. Folded into one.

### Parity tickets

- `prototype/node/work/1-inbox/FEAT-023-…` and
  `prototype/rails/work/1-inbox/FEAT-022-…`, ids taken from each journal's
  `Next ticket numbers > FEAT` counter, which is bumped, with a `defined`
  line in each journal. Outcome bullets are contract-level: the nine tables
  and prefixes, `store.view`, the event log beside the status machine,
  label printing and step completion, and the three seller surfaces. Neither
  prescribes its stack's implementation. Rails's Discovery notes name its
  outstanding §2.6 analytics-store gap, which `store.view` rides behind.

### Help articles

Checked all four against the merged branch.

- `shipping.md` claimed a step completion shows on "the order's activity
  feed" and that step completions keep the buyer's tracking current. Neither
  holds here: the feed component exists (FEAT-052) and nothing renders it
  yet — the order page draws `x-seller.flow-steps` alone — and the buyer's
  carrier and tracking come from `ShipmentController` marking the parcel
  shipped, not from the label step, which records them on the event and the
  label page. Rewritten, plus a line for Flow settings, which is linked
  unconditionally from every order page.
- `listings.md` claimed a photo is needed before a listing can go live.
  Nothing gates it: `ConfiguratorSectionNav` gives Photos no issue code and
  no `PublishIssue` code names one. It also said the left rail shows every
  missing piece; the rail marks the section with a dot and the hub's alert
  lists each issue with its fix link. Corrected, and "or made to order"
  restored — `ListingRequest` reads a `made_to_order` checkbox in place of a
  quantity.
- `messages.md`: the listing page's heading is "Questions & answers".
- `getting-paid.md` holds: escrow on payment, release on delivery,
  Monday–Sunday periods settled on the Monday (`PayoutPeriod::endingBefore`),
  a post-payout refund carried forward (§4.2), and a 10% fee
  (`Fee::PLATFORM_PERCENT`).

`HelpArticleControllerTest` pins the phrase "asks for a carrier and tracking
number", so `shipping.md` keeps it verbatim.

### Gate

The help-article commit touches `prototype/php` outside `work/` and `docs/`,
so the pre-commit hook ran `make precommit`: green, 4758 passed, 34861
assertions. Every other commit is docs and work files only.

---
id: FEAT-014
type: feature
status: open
created: 2026-08-23
---

# FEAT-014: Messaging seeds, smoke walk, docs, and final validation

## Problem
Once FEAT-009 to FEAT-013 land the feature exists without a record of it: `src/db/seeds/` creates no conversations or FAQs so a fresh `make up` shows empty inboxes; `docs/` has no `messaging.md` and `docs/architecture.md`'s deployables, layers, ER diagram, and notifications sections describe a two-actor app; `test/smoke_test.rb` walks the product without sending a message; `README.md`'s seeded accounts and feature list predate messaging.

## Goal
Someone evaluating this prototype can run it, see messaging working with seeded data, and read how it is designed, the same way they can for the Node prototype.

## Outcome
`make fresh` seeds at least one thread of each of the four kinds with messages and one published FAQ, and the seed is idempotent; the smoke test walks a shopper asking a question, the seller answering and publishing it, and the FAQ appearing on the listing page; `docs/messaging.md` exists with the same three questions the Node doc answers (how a question becomes an FAQ, who may read and post, where unread counts come from) drawn as Mermaid diagrams and referring to the Rails files; `docs/architecture.md`, `docs/data-model.md`, `docs/ontology.md`, and `README.md` describe three sites, the admin actor, messaging, the Hotwire stack, and the commands; `docs/review.md` is refreshed; the full suite and `make coverage` pass; a manual curl or browser walk of the seeded data is recorded in the ticket.

## Why it matters
The stack comparison is read as much as it is run. A feature with no seed, no smoke, and no doc is invisible to the reviewers who will weigh Rails against PHP and Node.

## Discovery notes
`src/db/seeds/order_history.rb` already builds a shipped fulfillment the `fulfillment` thread can hang off; `Seeds::Messaging` is the natural fifth seed file and `seeds_test.rb` asserts counts. The Node doc's structure (kinds table, three "Question:" sections each with a Mermaid diagram and a "Caveats" paragraph) is the one to mirror so the two docs compare side by side; use the `diagramming` skill. The architecture doc's "Sites" table gains the admin row, the layers section notes `app/channels` and `app/javascript`, the ER diagram gains `admins`, `conversations`, `messages`, `listing_faqs`. The README's "no JavaScript" sentence becomes a sentence about what the importmap loads and what works without it. The review doc's comparison with the other prototypes is where the Hotwire point is made — state facts (lines of JavaScript, gems added, what broadcasts), no adjectives.

## Related work
- FEAT-006 (seed data)
- FEAT-007 (docs and diagrams)
- FEAT-008 (final validation)
- FEAT-009
- FEAT-010
- FEAT-011
- FEAT-012
- FEAT-013
- prototype/node/docs/messaging.md
- prototype/node/work/3-done/FEAT-017-final-validation-and-documentation-refresh.md

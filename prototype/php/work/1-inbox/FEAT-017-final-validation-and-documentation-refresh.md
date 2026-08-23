---
id: FEAT-017
type: feature
status: open
created: 2026-08-23
---

# FEAT-017: Final validation and documentation refresh

## Problem
Seven tickets have added an admin actor, four messaging tables, three sites' worth of pages, seeded data, and the first JavaScript in the tree. `docs/architecture.md`, `docs/data-model.md`, `docs/identity.md`, `docs/review.md` and `README.md` all describe a two-actor, two-site, no-JavaScript application, and the test and coverage counts they quote are stale.

## Goal
The gate is green, the walk on real data holds, and every doc describes what is actually in the tree.

## Outcome
- `make check` passes: lint clean, PHPStan at `level: max` with no `excludePaths`, no `ignoreErrors` and no baseline, whole Pest suite green.
- `make coverage` reports the coverage of the tree and the number is recorded.
- `tests/SidecarsTest.php`'s exception list is still empty.
- On a freshly seeded stack, a walk over HTTP covers: an anonymous shopper asking on a listing, the seller seeing the badge move and replying, the seller publishing the answer, the answer appearing on the listing for everyone, a support thread opening from both sites and the admin answering it, a non-participant getting 404, and a blocked customer reading a thread with no reply box. The walk and its results are recorded on the ticket.
- `docs/architecture.md` describes three sites, three guards, three actors, the messaging tables, and the second event/listener pair.
- `docs/data-model.md`'s ER diagram includes `admins`, `customer_blocks`, `conversations`, `messages`, `listing_faqs`.
- `docs/identity.md` covers the admin actor and what a merge moves.
- `docs/messaging.md` matches the code, name for name.
- `docs/review.md` maps the messaging brief to the routes and tests that prove it, carries the corrected JavaScript claim, updates the counts, and holds a short honest note comparing this design against Node's on the points where they differ.
- `README.md`'s counts, seeded accounts, command table, and known gaps are current.

## Why it matters
The three prototypes are read side by side. A doc that describes the previous version costs more credibility than a missing feature.

## Discovery notes
- Every doc states the question its diagram answers and uses the real names from the code. Grep each doc against `src/app/` — that check is in `docs/README.md` and it is the fastest way to find a stale name.
- `docs/README.md` is an index table; `messaging.md` and any admin doc need a row.
- `docs/review.md` quotes exact numbers (tests, assertions, files linted, coverage). Take them from the run, not from memory. The "Engineering quality" table, the three requirement tables, and "Known gaps" all move.
- The comparison note against Node belongs in `review.md`, is short, and is honest in both directions. The points worth naming: the `subject_key` unique index against Node's pure `planConversation` match; the policy against Node's `conversationAccess` predicate plus route-level 404; the polling generator against Node's in-process emitter; whether Laravel's model binding and form requests bought real leverage over hand-rolled route schemas. Do not claim a win that the code does not support.
- `docs/architecture.md`'s Sites table, Authorization table, Notifications section, Testing section (the Pest binding list and the `Arch.php` `ignoring` list both changed), and Repository layout all have edits pending.
- The curl walk runs against the running stack on port 8000 from the prototype directory. `make fresh` first, and record the actual output.
- Risk: this ticket is where anything the previous seven left half-done surfaces. Run `make check` before touching a doc, so a green gate is the baseline rather than the goal.

## Related work
- FEAT-010 through FEAT-016. FEAT-008 and MAINT-002 (the previous validation-and-docs passes) are the shape to follow.

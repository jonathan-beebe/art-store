---
id: BUG-001
type: bug
status: resolved
created: 2026-08-23
---

# BUG-001: Customer merge can duplicate a thread and find-or-open then splits the pair

## Problem
`Customer#absorb` (`src/app/models/customer.rb:99-112`) moves an anonymous customer's conversations with `update_all(customer_id: id)`. When the verified customer already holds a thread of the same kind, participants, and subject, the merge produces two rows with the same shape; the `(kind, subject_type, subject_id)` index (`src/db/migrate/20260823000102_create_conversations.rb:22`) is not unique, and `Conversation.open`'s `find_or_create_by!` (`src/app/models/conversation.rb:52-58`) has no ORDER BY, so the next "message this seller" or "Contact support" lands on whichever duplicate SQLite returns first. Proved in a runner: a customer who asked about a listing signed in, asked again anonymously, and verified, ends with two identical threads in both inboxes, and a reply in the other thread is stranded. The missing uniqueness also leaves `find_or_create_by!` open to a double-submitted `button_to` creating two threads.

## Goal
One thread per (kind, participants, subject) holds after a merge and under concurrent opens, the way it holds on the first open.

## Outcome
Merging an anonymous customer who duplicates an existing thread leaves one conversation carrying both histories, with `last_message_at` and unread counts right, and both inboxes showing one row; `Conversation.open` returns a deterministic thread; the database refuses a second row of the same shape; a model test walks the duplicate-producing merge and the suite stays at 100% line coverage.

## Why it matters
The one-thread-per-subject rule is the invariant the inbox, find-or-open, and notifications rest on; `docs/messaging.md` states the merge "carries the thread over", which is only true when the verified side had none.

## Discovery notes
Folding duplicates inside `absorb` — move the anonymous thread's messages into the surviving thread, recompute `last_message_at`, delete the emptied row — keeps the invariant where the merge breaks it; `MERGED_ASSOCIATIONS`' generic `update_all` loop cannot express that, so conversations may need to leave the list for a merge method the association or `Conversation` owns. For the index: SQLite treats NULLs as distinct in a unique index, so a plain unique index over the six columns does not catch duplicates for kinds with a NULL column; a unique expression index over `COALESCE`d columns, or one partial unique index per kind, both work. `Conversation.open` ordering (`order(:id)` before `first_or_create!`, or `find_or_create_by!` with an ordered relation) settles determinism. The docs/messaging.md merge sentence should end up true.

## Related work
- FEAT-010
- FEAT-002 (merge)
- prototype/rails/work/3-done/FEAT-010-conversations-messages-and-listing-faqs-on-the-models.md

## Working

**Re-validated the problem.** A runner against the test database: a verified
customer with an answered question on a listing, the same listing asked about
again by an anonymous row, then `absorb`. Before: 2 threads for the verified
customer, 2 rows in the seller's inbox, `Conversation.open` returning id 1
while `Conversation.involving` ordered id 2 first, so the next "ask the seller"
landed on the thread the seller's reply was not in. After the change: 1 thread
in both inboxes, both histories on it, `open` returning that row.

**The index.** `index_conversations_on_shape`, unique over
`kind, COALESCE(seller_id, 0), COALESCE(customer_id, 0), COALESCE(admin_id, 0),
COALESCE(subject_type, ''), COALESCE(subject_id, 0)`. One expression index over
the six columns rather than four partial indexes per kind: one name, one rule,
and the schema dumper writes expression indexes for SQLite as-is (verified in
`db/schema.rb`). The existing `(kind, subject_type, subject_id)` index stays —
find-or-open reads the columns, and SQLite uses an expression index only for a
query written with the same expressions.

**The fold.** `conversations` left `Customer::MERGED_ASSOCIATIONS`, since the
generic `update_all(foreign_key => id)` cannot fold. `Conversation#move_to`
holds the decision: the thread of the same shape the receiving customer already
holds takes this one's messages and the later `last_message_at`, and this row is
destroyed; with no such thread the row changes hands. `Conversation::SHAPE` names
the six columns the index is over, and the private `#shape` reads them off the
row. `Customer#absorb` calls `move_to` for each of the anonymous customer's
threads after the association loop, inside the same transaction.

Unread state survives the fold because `read_at` is per message and the move is
`update_all(conversation_id:)` — nothing touches it. `sent_messages` stays in
`MERGED_ASSOCIATIONS`, so the anonymous customer's messages are re-pointed to
the verified sender and stop counting as unread for them.

**Determinism.** `Conversation.open` is `where(shape).order(:id).first ||
create!`. With the index in place duplicates cannot form; the ordering settles
what the method returns over the rows a database that predates the index
holds.

**Left alone.** `docs/identity.md` and `docs/ontology.md` describe the merge as
moving "favorites, carts, orders, listing events, and notifications" — true
before this change and true after, and neither ever named conversations.

**Verification.** `make test`: 744 runs, 2336 assertions, 0 failures, 0 errors,
line coverage 1242 / 1242 (100.00%). Baseline was 737 runs, 2317 assertions,
1231 / 1231. Seven tests added: two refusals from the index (a kind with a
subject, a kind with three null columns), four on `Conversation#move_to`
(change of hands, fold, the later `last_message_at`, read state), one on
`Customer#absorb` folding a duplicate. `make migrate` applied the index to the
development database.

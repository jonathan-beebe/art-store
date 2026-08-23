---
id: BUG-001
type: bug
status: open
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

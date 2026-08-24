---
id: IMPRV-010
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-010: Messaging and merge invariants learned from PHP and Rails

## Problem
`planConversation` decides find-or-open in app code only — two concurrent opens for the same subject create two threads (PHP has a unique `subject_key`, Rails a unique `COALESCE` expression index). `planCustomerMerge` re-points conversations wholesale, so a merge can leave one subject with two threads (PHP/Rails fold them), leaves `customer_blocks` on the anonymous row, and does not re-point `messages.sender_id`. `listing_faqs` has no uniqueness on `(listing_id, source_message_id)`, so publishing twice creates duplicate FAQ rows, and a thread shows no "published" marker.

## Goal
The database, and the merge, hold the same invariants in Node as in the other two prototypes.

## Outcome
One thread per subject is enforced by a unique index and the concurrent-open race is tested; merging folds duplicate conversations preserving per-message `read_at`, re-points blocks and sent messages, and a test asserts every `customer_id` column is either in the merge manifest or in an explicit left-behind list; `(listing_id, source_message_id)` is unique and a second publish is refused with the thread showing "Published to FAQ" with a link; docs updated.

## Why it matters
Two threads for one subject and duplicate FAQs are visible product bugs; the invariants are cheap once the shape is borrowed.

## Discovery notes
Rails' `COALESCE` expression index and PHP's `subject_key` both fit Kysely. The manifest test is the Rails `MERGED_ASSOCIATIONS` idea. Land after FEAT-018 so the index is written once over text ids.

## Related work
- RFCTR-002, RFCTR-004
- prototype/rails BUG-001 (merge duplicate threads)

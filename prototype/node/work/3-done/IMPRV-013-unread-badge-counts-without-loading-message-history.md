---
id: IMPRV-013
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-013: Unread badge counts without loading message history

## Problem
`unreadMessageCount` (`app/actions/messaging/conversation-inbox.ts:60-97`) answers the nav badge's single integer by loading every conversation row for the actor (`conversationsFor`, `selectAll`, no LIMIT) and then every message in every one of those conversations — including `body` text — ordered by `sentAt`, and counting unread in JS (`core/messaging/unread-messages.ts:31-42`). It is wired as a `preHandler` on all three sites (`sites/shop/index.ts:16`, `sites/seller/index.ts:32`, `sites/admin/index.ts:16`), so every page GET and POST for a signed-in actor pays O(total message history), and `node:sqlite` is synchronous, so the scan blocks the event loop.

The SSE badge stream multiplies it: `app/plugins/events.ts:48-50` emits `changed` after any successful non-GET anywhere in the app, and each open `/events` stream re-runs the same full load per `changed` (`events.ts:163-166`) — N open browsers × M writes = N×M full scans, serialized through the single connection.

In the same area, `markConversationRead` (`app/actions/messaging/mark-conversation-read.ts:22-38`) selects every message in the thread, filters with `isUnreadBy` in JS, then updates by id list inside a `BEGIN IMMEDIATE` transaction.

The only messages index is `(conversation_id, sent_at)` (`migrations/20260823000008-create-messaging.ts:84-86`); nothing serves an unread lookup by `read_at`.

## Goal
The badge and its live refresh cost a fixed amount of work that does not grow with message history.

## Outcome
Rendering any page, and each SSE refresh, issues a bounded number of statements whose cost scales with unread messages rather than total history; the badge shows the same number it does today, and marking a thread read produces the same end state — both pinned by tests against the existing pure rules.

## Why it matters
This is the hottest shared path in the app — every authenticated page view on all three sites plus every SSE wake — and its cost grows without bound as messages accumulate. All four performance research passes independently ranked it the top finding. The synchronous driver means the waste is event-loop blocking, so it taxes every concurrent request, not just the one asking.

## Discovery notes
- One aggregate query fits: `messages` joined to the actor's `conversations` participant column, `read_at IS NULL` and not sent by the actor, `count(*)`. The "unread = not read and not mine" rule could stay single-sourced via a small pure predicate helper, or the SQL restatement gets a characterization test against `isUnreadBy`.
- A `(conversation_id, read_at)` index — possibly partial, `WHERE read_at IS NULL` — makes the count O(unread).
- The JS fold stays for `inboxConversations`, which genuinely needs per-conversation rows (though it fetches full bodies only for previews — worth a look while there).
- `markConversationRead` could be a single `UPDATE ... WHERE conversation_id = ? AND read_at IS NULL AND NOT (sender_type = ? AND sender_id = ?)`; `numAffectedRows` gives the return value and the transaction may become unnecessary.
- Longer term the `changed` emission could carry which actor's count moved so only affected streams re-read; fixing the count query alone may make that unnecessary.

## Working
- 2026-08-25 re-validated: `unreadMessageCount` (`app/actions/messaging/conversation-inbox.ts:60-97`) still loads all conversations then all messages with bodies and folds in JS; `markConversationRead` still selects the whole thread and updates by id list in a transaction; the only messages index is `(conversation_id, sent_at)`.
- Plan: (1) partial index `messages(conversation_id) WHERE read_at IS NULL`, rewritten into the existing messaging migration per alignment §1 ("existing migrations may be rewritten in place"; rebuild with `make fresh`); (2) `unreadMessageCount` becomes one aggregate `count(*)` joining messages to the actor's participant column with `read_at IS NULL AND NOT (sender = actor)`; (3) `markConversationRead` becomes one `UPDATE ... WHERE conversation_id = ? AND read_at IS NULL AND NOT (sender = actor)` returning `numUpdatedRows`, transaction dropped.
- The SQL restates the `isUnreadBy` rule; a characterization test pins the aggregate against the pure fold over a mixed seed.
- Out of scope: `inboxConversations` preview/body fetch, `events.ts` `changed` fan-out targeting.
- 2026-08-25 delivered: partial index `messages_conversation_id_unread_index` on `messages(conversation_id) WHERE read_at IS NULL`, written into the messaging migration per alignment §1 (`sql.ref('read_at')` in the partial `WHERE` — Kysely's create-index `where` takes a bare column name only for columns in the index itself); dev database rebuilt with `make fresh`. `unreadMessageCount` is one join+`count(*)` through `toCount`; `markConversationRead` is one `UPDATE` returning `numUpdatedRows`, transaction dropped. Characterization tests added first (8 new: customer/admin actors, read-drop, cross-conversation isolation, and a pin of the SQL count against `isUnreadBy` over raw rows). `make check` green: 1925 tests (was 1917), coverage 99.43% lines / 95.89% branches.

## Related work
- FEAT-007 (messaging center), FEAT-016 (SSE unread badge)
- IMPRV-014 (hook ordering on the storefront interacts with the unread hook)
- IMPRV-021 (per-request hook overhead generally)

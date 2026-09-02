---
id: FEAT-040
type: feature
status: open
created: 2026-09-01
---

# FEAT-040: Threads carry a title, a status, a reply-to, and a shared desk

## Problem
The messaging store (`app/Domain/Messaging`, `app/Models/Conversation.php`, `app/Models/Message.php`, `database/migrations/*_create_messaging_tables.php`) holds one find-or-open thread per subject for all four kinds, with no title, no open/resolved state, and no way for a message to name the one it answers. Support threads bind to one admin row (`Admin::platformAdmin()`, `Conversation::withParticipant` on `admin_id`), so the second admin never sees them and an admin cannot see seller ↔ customer threads at all. `OpenConversationWithMessage` and the listing-question route admit an anonymous cookie customer.

## Goal
The domain, schema, actions, policy, and notifications described in `docs/messaging.md` exist and are tested, so the three site lanes (FEAT-041, FEAT-042, FEAT-043) build screens on a settled foundation.

## Outcome
- A thread has a nullable `title`, a nullable unique `subject_key` (set only on `fulfillment` threads), a nullable `order_id`, `resolved_at` and a `resolved_by` morph pair; a message has a nullable `reply_to_message_id`. The migration is edited in place and `make fresh` rebuilds.
- `ConversationKind` answers `opensFresh()`, `contextColumns()`, `isDesk()`, `resolvableBy(ActorType)`; `ConversationStatus` (open/resolved) and `ThreadTitle` (max 120, `fromBody`) exist as pure domain types; `ConversationSubject` keeps only the fulfillment factory and `for()`, and `ThreadOpening` names a fresh thread's kind, sides, title, and context.
- `OpenThread` opens a fresh titled thread with its first message in one transaction and replaces `OpenConversationWithMessage`; `PostMessage` accepts an optional reply-to from the same thread, applies the reopen rule, and stamps `admin_id` on a desk thread's first admin reply; `ResolveConversation` and `ReopenConversation` exist, refuse a no-op with `DomainRuleViolation`, log `conversation.resolve` / `conversation.reopen`, and resolving notifies the supported side with `ConversationResolved`; `PublishListingFaq` resolves its source thread.
- `ConversationPolicy`: an admin views every thread and posts only on desk kinds; sellers and customers view their own and post while in standing; `resolve` / `reopen` follow `resolvableBy` and the current status.
- `Message::unreadBy(Admin)` treats every admin as one desk; `Conversation::withParticipant(Admin)` is the two desk kinds; an oversight scope lists seller ↔ customer threads for the admin; scopes exist for status, kind filters, unread-only, the seller's unanswered-questions ordering, and the desk's needs-reply queue.
- `NotifyOfMessage` sends to every admin when the desk is the other side; `ActorDisplay::SUPPORT_DESK` names the desk; `Conversation::counterpartName` uses it.
- `MergeAnonymousCustomer` still moves threads: fresh threads take the new `customer_id`; fulfillment threads rebuild their key and fold.
- `StoryEvent` gains `conversation.resolve` and `conversation.reopen`; `docs/alignment.md` §2.3 lists them and §8 records the round (PHP ships, Node and Rails owe).
- `MessagingSeeder` seeds the richer demo: two listing questions (one answered and published, one unanswered), the fulfillment thread, a resolved seller support thread with a title and an admin reply, an open customer support thread tied to an order, and one message that replies to another — Harry Potter characters only.
- Existing controllers and views keep compiling against the new shapes (a thin pass; the screens themselves are the site lanes'), `make precommit` is green, coverage stays at 100%.

## Why it matters
Every screen the three portals need — titled threads, resolve, reply-to, the desk that sees all, the questions queue — rests on these shapes. Building them once, tested, keeps the three lanes from each inventing a piece of the domain.

## Discovery notes
`docs/messaging.md` is the design of record: the kinds table, the ER diagram, the opening flowchart, the policy flowchart, the status state diagram, the notification flow, and the scopes are all specified there. The design canvas "Art Store Messaging" shows the screens the shapes serve. Memory: migrations are editable in place here (`make fresh`), and seeds use Harry Potter characters only. The existing tests for `ConversationSubject`, `OpenConversationWithMessage`, `ConversationPolicy`, and the seeders will need to move with the shapes rather than be deleted wholesale — the behaviours they protect (find-or-open under contention for fulfillment, the rolled-back blocked ask, 404-as-not-found) still hold.

## Related work
- FEAT-010 … FEAT-017 (the first messaging round)
- FEAT-041, FEAT-042, FEAT-043 (the site lanes that build on this)

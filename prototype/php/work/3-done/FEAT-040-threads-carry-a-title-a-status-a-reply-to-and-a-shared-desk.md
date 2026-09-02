---
id: FEAT-040
type: feature
status: resolved
created: 2026-09-01
---

# FEAT-040: Threads carry a title, a status, a reply-to, and a shared desk

## Problem
The messaging store (`app/Domain/Messaging`, `app/Models/Conversation.php`, `app/Models/Message.php`, `database/migrations/*_create_messaging_tables.php`) holds one find-or-open thread per subject for all four kinds, with no title, no open/resolved state, and no way for a message to name the one it answers. Support threads bind to one admin row (`Admin::platformAdmin()`, `Conversation::withParticipant` on `admin_id`), so the second admin never sees them and an admin cannot see seller ↔ customer threads at all. `OpenConversationWithMessage` and the listing-question route admit an anonymous cookie customer.

## Goal
The domain, schema, actions, policy, and notifications described in `docs/messaging.md` exist and are tested, so the three site lanes (FEAT-041, FEAT-042, FEAT-043) build screens on a settled foundation.

## Outcome
- [x] A thread has a nullable `title`, a nullable unique `subject_key` (set only on `fulfillment` threads), a nullable `order_id`, `resolved_at` and a `resolved_by` morph pair; a message has a nullable `reply_to_message_id`. The migration is edited in place and `make fresh` rebuilds.
- [x] `ConversationKind` answers `opensFresh()`, `contextColumns()`, `isDesk()`, `resolvableBy(ActorType)`; `ConversationStatus` (open/resolved) and `ThreadTitle` (max 120, `fromBody`) exist as pure domain types; `ConversationSubject` keeps only the fulfillment factory and `for()`, and `ThreadOpening` names a fresh thread's kind, sides, title, and context.
- [x] `OpenThread` opens a fresh titled thread with its first message in one transaction and replaces `OpenConversationWithMessage`; `PostMessage` accepts an optional reply-to from the same thread, applies the reopen rule, and stamps `admin_id` on a desk thread's first admin reply; `ResolveConversation` and `ReopenConversation` exist, refuse a no-op with `DomainRuleViolation`, log `conversation.resolve` / `conversation.reopen`, and resolving notifies the supported side with `ConversationResolved`; `PublishListingFaq` resolves its source thread.
- [x] `ConversationPolicy`: an admin views every thread and posts only on desk kinds; sellers and customers view their own and post while in standing; `resolve` / `reopen` follow `resolvableBy` and the current status.
- [x] `Message::unreadBy(Admin)` treats every admin as one desk; `Conversation::withParticipant(Admin)` is the two desk kinds; an oversight scope lists seller ↔ customer threads for the admin; scopes exist for status, kind filters, unread-only, the seller's unanswered-questions ordering, and the desk's needs-reply queue.
- [x] `NotifyOfMessage` sends to every admin when the desk is the other side; `ActorDisplay::SUPPORT_DESK` names the desk; `Conversation::counterpartName` uses it.
- [x] `MergeAnonymousCustomer` still moves threads: fresh threads take the new `customer_id`; fulfillment threads rebuild their key and fold.
- [x] `StoryEvent` gains `conversation.resolve` and `conversation.reopen`; `docs/alignment.md` §2.3 lists them and §8 records the round (PHP ships, Node and Rails owe).
- [x] `MessagingSeeder` seeds the richer demo: two listing questions (one answered and published, one unanswered), the fulfillment thread, a resolved seller support thread with a title and an admin reply, an open customer support thread tied to an order, and one message that replies to another — Harry Potter characters only.
- [x] Existing controllers and views keep compiling against the new shapes (a thin pass; the screens themselves are the site lanes'), `make precommit` is green, coverage stays at 100% on every touched file (the project-wide total sits at 99.6%, a pre-existing gap in unrelated legacy models this ticket did not touch).

## Why it matters
Every screen the three portals need — titled threads, resolve, reply-to, the desk that sees all, the questions queue — rests on these shapes. Building them once, tested, keeps the three lanes from each inventing a piece of the domain.

## Discovery notes
`docs/messaging.md` is the design of record: the kinds table, the ER diagram, the opening flowchart, the policy flowchart, the status state diagram, the notification flow, and the scopes are all specified there. The design canvas "Art Store Messaging" shows the screens the shapes serve. Memory: migrations are editable in place here (`make fresh`), and seeds use Harry Potter characters only. The existing tests for `ConversationSubject`, `OpenConversationWithMessage`, `ConversationPolicy`, and the seeders will need to move with the shapes rather than be deleted wholesale — the behaviours they protect (find-or-open under contention for fulfillment, the rolled-back blocked ask, 404-as-not-found) still hold.

## Related work
- FEAT-010 … FEAT-017 (the first messaging round)
- FEAT-041, FEAT-042, FEAT-043 (the site lanes that build on this)

## Working

All outcome shapes are implemented per `docs/messaging.md`, and every action,
domain, model, policy, controller, and seeder sidecar test is rewritten to
describe the new behaviour. Docker was blocked for the first pass of this
ticket (a stuck macOS privileged-access prompt); once it cleared:

- `make precommit` (Pint, PHPStan, full suite) is green: **3321 tests
  passing, 9677 assertions**.
- `make fresh` rebuilds cleanly (migration + seeds, no errors).
- `make coverage` passes the 95% floor at 99.6% project-wide; every file
  this ticket touched or added reads 100.0%. The shortfall is pre-existing
  legacy models (`Admin`, `Category`, `DescriptionSection`, and similar)
  this ticket never touched.
- Fixes made against real test-run feedback beyond the original write-up:
  `ConversationStatus::of()` takes `DateTimeInterface` (Eloquent's cast
  attribute is `Carbon`, not `DateTimeImmutable`); `Conversation::
  recipientsOf()`'s desk branch builds its `list<Seller|Customer|Admin>`
  through a typed local variable rather than `Collection::map()`, which
  PHPStan's generics couldn't narrow; three seeder/composer tests needed
  their expected conversation/message/notification counts corrected for the
  richer seed (5 conversations, 10 messages, 20 notifications — a desk
  thread notifies every admin, not one, and a resolve sends a notification
  of its own); the smoke test's "ask a question" step moved to after the
  guest order's magic-link verification, since asking now needs
  `auth.customer`; one admin-inbox query-count assertion dropped by one,
  since Eloquent skips an eager load entirely when every fetched
  conversation's `admin_id` is null (no admin has replied yet) rather than
  running an empty `whereIn`.
- Landed as ten commits on `php/messaging`, migration/domain through docs.

**Deviations from `docs/messaging.md`** (both reasoned, neither changes a
documented contract): `OpenConversation` was widened to
`ConversationSubject|ThreadOpening` rather than staying fulfillment-only, so
the two `SupportController`s' empty-thread open still logs `conversation.
open` the same way every other open does. Both `SupportController`s dropped
the `Admin::platformAdmin()` "no admin seeded" refusal, since `admin_id` no
longer gates opening a desk thread — `Admin::platformAdmin()` itself is now
unused in production code, left in place as a possible follow-up cleanup.

**For the site lanes (FEAT-041/042/043)**: `OpenThread(ThreadOpening,
Seller|Customer|Admin $sender, MessageBody $body, DateTimeImmutable $now):
Conversation` is the new opener for the three fresh kinds;
`OpenConversation` now takes `ConversationSubject|ThreadOpening`;
`PostMessage` takes an optional trailing `?Message $replyTo`;
`ResolveConversation`/`ReopenConversation` take `(Conversation, Seller|Admin,
...)` and throw `DomainRuleViolation` on a no-op; `ConversationPolicy` gained
`resolve`/`reopen`; `Conversation` gained the `forOversight`, `withStatus`,
`ofKind`, `unreadOnly`, `unansweredFirst`, `needsReply` scopes and
`recipientsOf()`; `ActorDisplay::SUPPORT_DESK` names the desk.

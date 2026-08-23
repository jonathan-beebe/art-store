---
id: RFCTR-002
type: refactor
status: resolved
created: 2026-08-23
---

# RFCTR-002: Messaging, naming, and listing decisions move into core

## Problem
Several presentation and business rules are implemented more than once,
partly or wholly outside `app/core`, and one core type permits states the
data never actually holds.

**`shopName()` is bypassed by three inline fallbacks that give a different
answer.** `app/sites/admin/queries/ledger-rows.ts:52`,
`payout-rows.ts:46`, and `seller-accounts.ts:48` all write
`row.shopName ?? row.email`. Core's `shopName` (used correctly at
`fulfillment-rows.ts:47`, `listing-rows.ts:77`, `listing-detail.ts:56`)
trims and falls back to `seller.email.split('@')[0]`. The three inline
sites fall back to the whole address and treat a whitespace-only
`shopName` as a real name — the same seller reads as `anna` on the
fulfillments table and `anna@example.com` on the ledger, payouts, and
seller dropdowns.

**Storefront visibility is a core predicate and, separately, hand-written
SQL in two dialects.** `app/core/listings/listing-availability.ts:7-9`
(`isOnStorefront`) says `!hasActiveRemoval && status in ['for_sale','sold']`.
`app/sites/shop/queries/find-storefront-listings.ts:27-40` (`isBrowsable`)
says `status = 'for_sale' AND NOT EXISTS(unlifted removal)`.
`app/sites/shop/queries/find-favorite-listings.ts:30-41` writes a third
variant, then maps rows through `toStorefrontListing`, which hard-codes
`isPurchasable(status, quantity, false)` at line 123 because the SQL
already dropped removals. Three statements of one rule, only one of them
tested without a database.

**The FAQ prefill rule lives in a seller route.**
`app/sites/seller/routes/messages.ts:18-27` — `faqPrefillFrom` decides
what a published FAQ entry is made of: `thread.messages[0]` is the
question, `[...thread.messages].reverse().find((message) => message.isMine)`
is the answer, and its id is the `sourceMessageId`. A domain decision
about listing FAQs, expressed as array manipulation inside a route module.

**"Support is the first admin by id" is a raw query in two route files.**
`app/sites/seller/routes/messages.ts:80-91` and
`app/sites/shop/routes/messages.ts:117-134` both run
`db.selectFrom('admins').select('id').orderBy('id').limit(1).executeTakeFirst()`
inline and branch on absence with their own copy of the flash-and-redirect.

**The activity window is computed twice, two different ways.**
`app/sites/seller/routes/listings.ts:36-37,195-201` defines
`ACTIVITY_WINDOW_MS = 14 * 24 * 60 * 60 * 1000` and queries events
`since new Date(now.getTime() - ACTIVITY_WINDOW_MS)`, then renders
`activityTimeline(dailyCounts, { endsOn: now, days: 14 })`
(`app/core/reports/activity-timeline.ts:6-10,30-32`), which walks back
with `shiftUtcDays(endsOn, index - 13)`. The query's window starts a full
day earlier than the timeline's first row, so one day of events is
fetched and silently discarded — a fixed-millisecond subtraction is not
the same operation as a UTC-day shift.

**Pure view-model functions live in `app/actions/`.**
`app/actions/messaging/conversation-thread.ts:82-96` (`toThreadMessage`,
including `isMine: message.senderType === actor.type && message.senderId === actor.id`
— an ownership predicate written inline one line above a call to core's
`isUnreadBy`, which answers the same shape of question).
`app/actions/messaging/conversation-participants.ts:15,56-74`
(`ABSENT_COUNTERPART`, `counterpartName`, `senderName` — naming rules that
sit beside core's `customerName`/`shopName` but in the coordination layer).

**Display-name fallbacks are written inline in admin page titles.**
`app/sites/admin/routes/customers.ts:26`
(`detail.customer.email ?? \`Customer ${detail.customer.id}\``) and
`app/sites/admin/routes/sellers.ts:20`
(`detail.seller.shopName ?? detail.seller.email`). Core already answers
both — `customerName` (produces `Guest #<id>`) and `shopName` — so the
same person is named differently on the admin page and in the messaging
inbox.

**The merge writer re-derives what the plan already decided.**
`app/actions/customers/merge-anonymous-customer.ts:107-125` and
`app/core/customers/customer-merge-plan.ts:37-42` — `planCustomerMerge`
returns the de-duplicated `favoriteListingIds`. `applyFavorites` then
recomputes which of them are new:
`const alreadyFavorited = new Set(favorites.verified); const moving = plan.favoriteListingIds.filter((listingId) => !alreadyFavorited.has(listingId))`.
The dedupe decision is made in core and made again in the writer.

**A conversation's shape is a runtime table where it should be a
discriminated union.** `app/core/messaging/conversation-subject.ts:13-20`
(`ConversationOpening`), `:38-46` (`missingConversationParts`);
`app/core/messaging/conversation-kind.ts:29-34` (`KIND_SHAPES`);
`app/actions/messaging/open-conversation.ts:33-36`;
`app/db/commerce-schema.ts:177-191`. `ConversationOpening` is
`{ kind; sellerId?; customerId?; adminId?; listingId?; fulfillmentId? }` —
all five optional and nullable. The rule ("`kind` decides which two
participant columns are filled and which subject column, if any") is
enforced only at runtime by `KIND_SHAPES` plus `missingConversationParts`,
throwing a `TypeError` in `openConversation`.
`openConversation({ kind: 'listing_question', customerId })` with no
`sellerId` and no `listingId` compiles cleanly.

**`ConversationActor.isBlocked` is optional where it is really
per-variant.** `app/core/messaging/conversation-access.ts:6,:33`;
`app/actions/messaging/conversation-actor.ts:17-19` —
`{ type: ActorType; id: number; isBlocked?: boolean }` with a comment
that only a customer can be blocked, and
`mayPost: mayRead && actor.isBlocked !== true`, so an unset flag reads as
"not blocked." `conversationActor` always sets it today, so the fallback
is unreachable in practice, but the type permits a customer whose
standing was never loaded to post.

**`ListingRemovedFilter` is declared twice.**
`app/sites/admin/queries/listing-rows.ts:8`
(`type ListingRemovedFilter = 'any' | 'removed' | 'visible'`) and
`app/sites/admin/routes/listings.ts:10`
(`const REMOVED_FILTERS = ['any', 'removed', 'visible'] as const`) — the
union and the runtime array are written independently in two files,
linked only by an assignment that happens to typecheck.

**`MagicLinkStatus` is a bare literal union.**
`app/core/auth/magic-link-status.ts:3` — `'usable' | 'expired' | 'consumed'`
with no runtime array, breaking the `as const` array pattern every
sibling union follows (`listing-status.ts:3-4`, `order-status.ts:5-15`,
`decline-reason.ts:1-2`).

**`[...].reverse().find()` where `findLast` exists.**
`app/sites/seller/routes/messages.ts:20` —
`const lastFromSeller = [...thread.messages].reverse().find((message) => message.isMine)`
copies and reverses the whole array to read one element.
`Array.prototype.findLast` has shipped since Node 18.

## Goal
Each of these rules — a seller's display name, storefront visibility, the
FAQ prefill, the support conversation lookup, the activity window,
message ownership, participant naming, favorite-merge dedupe, and a
conversation's required fields per kind — has exactly one implementation,
in core where it is a business or presentation rule.

## Outcome
- One `shopName`/`customerName` answer is used everywhere a seller or
  customer is displayed.
- Storefront status lists are exported from core and drive the SQL in
  every query that filters by them.
- `faqPrefill`, `activityWindow`, `isSentBy`, and participant naming are
  core functions with their own tests.
- One `openSupportConversation` action replaces the two duplicated inline
  queries.
- `ConversationOpening` is a discriminated union over `kind`, with each
  variant's required ids non-optional, and `missingConversationParts` is
  gone.

## Why it matters
Business and presentation rules live in core once; the same rule
implemented in two or three layers is what "abstraction only for
duplication felt three times" exists to catch, and `shopName`,
storefront visibility, and the activity window each hit that threshold.
Illegal states unrepresentable is the standard `ConversationOpening` and
`ConversationActor.isBlocked` currently fail: both compile in shapes the
data never actually holds, and the only thing catching the invalid case
today is a runtime throw. `findLast` is a named platform-wins item —
Node ships it, so the copy-and-reverse is unnecessary.

## Discovery notes
Call `shopName(row)` at the three inline sites — `sellerOptions` already
selects `shopName` and `email`, so each is close to a one-line change.
Export status lists from core (e.g. `STOREFRONT_STATUSES`,
`BROWSABLE_STATUSES`) and build each query's `where` from them, since SQL
cannot call a TypeScript predicate directly; leave a comment on the SQL
naming the core predicate it mirrors. Move `faqPrefillFrom` into
`app/core/messaging/faq-prefill.ts` taking
`readonly { id, body, isMine }[]` and returning the prefill, with tests
for the empty-thread and no-seller-reply cases. Add one action —
`openSupportConversation(context, { actorType, actorId })` — that finds
the counterpart admin, opens the conversation, and returns either the
conversation or a "no admin" result; both routes shrink to a few lines
each. Export `activityWindow(endsOn, days): { since: Date; days: number }`
from core beside `activityTimeline`, built on the same `shiftUtcDays`, and
have the route pass its result to both the query and the timeline. Move
`isMine` into core as `isSentBy(message, actor)` beside `isUnreadBy`, and
move `counterpartName`/`senderName`/`ABSENT_COUNTERPART` into
`app/core/messaging/participant-name.ts`. Call `customerName`/`shopName`
directly in the two admin page-title sites rather than re-deriving a
fallback. Have `planCustomerMerge` return what the writer needs directly
(`favoritesToMove`/`favoritesToDrop`) so `applyFavorites` only issues the
two statements.

Make `ConversationOpening` a union over `kind` with the required ids
non-optional per variant; `conversationSubject()` stays as the flattener
into the row shape, `missingConversationParts` and its throw disappear,
and `KIND_SHAPES` survives only to drive the `where` clauses in
`conversationsOnSubject`. Split `ConversationActor` into
`{ type: 'customer'; id: number; isBlocked: boolean } | { type: 'seller' | 'admin'; id: number }`,
and have `mayPost` read `actor.type !== 'customer' || !actor.isBlocked`.
Move `REMOVED_FILTERS` next to `ListingRemovedFilter` and derive the type
from it. `MagicLinkStatus` can stay a bare union or gain a matching array
for symmetry — low-stakes either way. Replace the reverse-and-find in
`seller/routes/messages.ts:20` with `thread.messages.findLast((message) => message.isMine)`.

Files this ticket is expected to touch: `app/core/shop/shop-name.ts`,
`app/core/listings/listing-availability.ts`,
`app/core/messaging/faq-prefill.ts` (new),
`app/core/messaging/participant-name.ts` (new),
`app/core/messaging/conversation-subject.ts`,
`app/core/messaging/conversation-kind.ts`,
`app/core/messaging/conversation-access.ts`,
`app/core/reports/activity-timeline.ts`,
`app/core/customers/customer-merge-plan.ts`,
`app/actions/messaging/open-conversation.ts` (new
`open-support-conversation.ts`), `app/actions/messaging/conversation-thread.ts`,
`app/actions/messaging/conversation-participants.ts`,
`app/actions/messaging/conversation-actor.ts`,
`app/actions/customers/merge-anonymous-customer.ts`,
`app/sites/admin/queries/ledger-rows.ts`,
`app/sites/admin/queries/payout-rows.ts`,
`app/sites/admin/queries/seller-accounts.ts`,
`app/sites/shop/queries/find-storefront-listings.ts`,
`app/sites/shop/queries/find-favorite-listings.ts`,
`app/sites/seller/routes/messages.ts`,
`app/sites/shop/routes/messages.ts`,
`app/sites/seller/routes/listings.ts`,
`app/sites/admin/routes/customers.ts`, `app/sites/admin/routes/sellers.ts`,
`app/sites/admin/queries/listing-rows.ts`,
`app/sites/admin/routes/listings.ts`, `app/core/auth/magic-link-status.ts`.

This ticket, along with RFCTR-001 and RFCTR-003, must land before
IMPRV-002 (validation declared on routes) — IMPRV-002 touches the same
route handlers this ticket is pulling logic out of, and doing that pull
first keeps IMPRV-002's route-body changes small and stable.

## Related work
- 03-core-shell.md — "`shopName()` bypassed by three inline fallbacks that give a different answer"
- 03-core-shell.md — "Storefront visibility is a core predicate and, separately, hand-written SQL in two dialects"
- 03-core-shell.md — "The FAQ prefill rule lives in a seller route"
- 03-core-shell.md — "'Support is the first admin by id' is a raw query in two route files"
- 03-core-shell.md — "The activity window is computed twice, two different ways"
- 03-core-shell.md — "Pure view-model functions living in `app/actions/`"
- 03-core-shell.md — "Display-name fallbacks written inline in admin page titles"
- 03-core-shell.md — "The merge writer re-derives what the plan already decided"
- 02-types-boundaries.md — "A conversation's shape is a runtime table where it should be a discriminated union"
- 02-types-boundaries.md — "`ConversationActor.isBlocked` is optional where it is really per-variant"
- 02-types-boundaries.md — "`ListingRemovedFilter` declared twice"
- 02-types-boundaries.md — "`MagicLinkStatus` is a bare literal union"
- 01-deps-platform.md — "`[...].reverse().find()` where `findLast` exists"
- IMPRV-002 (validation on routes) depends on this ticket landing first

## Working

Scope change: skipped the sub-item "Storefront visibility is a core predicate
and, separately, hand-written SQL in two dialects" — `isOnStorefront` /
`STOREFRONT_STATUSES` / `BROWSABLE_STATUSES` and the three query files
(`find-storefront-listings.ts`, `find-favorite-listings.ts`,
`listing-availability.ts`). Per the orchestrator's brief, another ticket
absorbs it. `app/core/listings/listing-availability.ts` was left untouched.

Verified against the code: every other sub-item in the Problem section still
applied as described, including the whitespace-only `shopName ?? email`
inline fallback at `seller-accounts.ts:48` (`sellerOptions`), which is a
second occurrence of the same bug beyond the three the ticket names at
ledger-rows.ts/payout-rows.ts, since `sellerAccounts` reads its seller names
through `sellerOptions`.

Changed:
- `app/core/messaging/faq-prefill.ts` (new) — `faqPrefill(messages)`, moved
  out of `seller/routes/messages.ts`'s `faqPrefillFrom`, using `findLast`.
- `app/core/messaging/participant-name.ts` — `counterpartName`, `senderName`,
  `ABSENT_COUNTERPART`, `ParticipantNames` moved in from
  `actions/messaging/conversation-participants.ts`, which now only reads the
  database and re-exports the type. Callers (`conversation-thread.ts`,
  `conversation-inbox.ts`, and both test files) import the pure functions
  from core directly.
- `app/core/messaging/conversation-subject.ts` — `ConversationOpening` is a
  discriminated union over `kind` with each variant's ids non-optional;
  `conversationSubject` flattens it with a `switch`; `missingConversationParts`
  and its `TypeError` are gone (the shape is now enforced at compile time).
  `open-conversation.ts` no longer checks for missing parts and builds its
  `where` clause from `participantColumnsOf`/`subjectColumnOf`
  (`conversation-kind.ts`) instead of a flat 5-column list — `KIND_SHAPES`
  stays module-private, reached through those two existing accessors rather
  than exported directly, since nothing outside `conversation-kind.ts` needs
  the table itself.
- `app/core/messaging/conversation-access.ts` — `ConversationActor` is now
  `{ type: 'customer'; id; isBlocked: boolean } | { type: 'seller' | 'admin'; id }`.
  Added `ConversationParticipant` (`{ type; id }`, no standing) as the
  parameter type for `isConversationParticipant`/`otherParticipants` and (via
  `unread-messages.ts`) `isSentBy`/`isUnreadBy` — those never needed
  `isBlocked`, and requiring it there would have forced every unread-count and
  notification call site to fabricate a standing it doesn't have.
  `conversationAccess`'s `mayPost` reads
  `actor.type !== 'customer' || !actor.isBlocked`. `conversation-actor.ts`
  builds `{ type: actor.type, id: actor.id }` for seller/admin (no field to
  forget to set) and only fills `isBlocked` for a customer.
- `app/core/messaging/unread-messages.ts` — added `isSentBy(message, actor)`;
  `isUnreadBy` is now `readAt === null && !isSentBy(...)`.
  `conversation-thread.ts`'s `toThreadMessage` calls `isSentBy` instead of
  its inline `senderType === actor.type && senderId === actor.id`.
- `app/core/reports/activity-timeline.ts` — added
  `activityWindow(endsOn, days): { since, days }`, built on the same
  `shiftUtcDays` as `activityTimeline`, `since` at UTC midnight of the
  timeline's first day. `seller/routes/listings.ts`'s `show` route replaced
  `ACTIVITY_WINDOW_MS` (a fixed-millisecond subtraction) with
  `activityWindow(now, ACTIVITY_WINDOW_DAYS)` and passes `window.since` to
  the query and `window.days` to `activityTimeline`, so the two can no longer
  drift.
- `app/core/customers/customer-merge-plan.ts` — `planCustomerMerge` returns
  `favoritesToMove`/`favoritesToDrop` (anonymous favorites partitioned against
  what the verified customer already has) instead of the deduped
  `favoriteListingIds` union. `merge-anonymous-customer.ts`'s `applyFavorites`
  now only deletes `plan.favoritesToDrop` and repoints the rest — it no
  longer recomputes the same set the plan already decided, and no longer
  takes the raw `favorites` read as a parameter.
- `app/core/auth/magic-link-status.ts` — added `MAGIC_LINK_STATUSES` as-const
  array; `MagicLinkStatus` is now derived from it.
- `app/actions/messaging/open-support-conversation.ts` (new) —
  `openSupportConversation(context, { actorType, actorId })`, returning
  `{ outcome: 'opened'; conversation } | { outcome: 'no-admin' }`. Both
  `seller/routes/messages.ts`'s and `shop/routes/messages.ts`'s `/support`
  handlers now call it instead of running the "first admin by id" query and
  the not-found branch inline; each keeps its own flash copy and redirect
  target, since those differ per site.
- `app/sites/seller/routes/listings.ts` — the activity-window lines in `show`
  use `activityWindow`; `ACTIVITY_WINDOW_MS` is deleted.
- `app/sites/admin/queries/ledger-rows.ts`, `payout-rows.ts`,
  `seller-accounts.ts` (`sellerOptions`) — all three now call `shopName(row)`
  instead of `row.shopName ?? row.email`.
- `app/sites/admin/routes/customers.ts` — the detail page title calls
  `customerName(detail.customer)` (so an anonymous customer now titles
  `Guest #<id>` instead of `Customer <id>`) instead of
  `detail.customer.email ?? \`Customer ${id}\``.
- `app/sites/admin/routes/sellers.ts` — the detail page title calls
  `shopName(detail.seller)` instead of `detail.seller.shopName ?? detail.seller.email`.
- `app/sites/admin/queries/listing-rows.ts` — added
  `REMOVED_FILTERS = [...] as const` beside `ListingRemovedFilter`, which is
  now derived from it. `app/sites/admin/routes/listings.ts` imports the array
  instead of declaring its own copy.

Left alone: `MessagingActor` (`actions/messaging/conversation-actor.ts`,
`{ type: ActorType; id: number }`) and the new core `ConversationParticipant`
are structurally identical. Not merged, to keep core's types free of an
actions-layer import — a duplicate shape across the two layers, not a
duplicate declaration one layer could just reuse.

Consequential fix outside the listed file set: `app/plugins/unread-messages.test.ts`
built a `ConversationOpening` from a loosely-typed helper parameter
(`{ kind: 'admin_seller' | 'admin_customer'; sellerId?; customerId? }`); the
new discriminated union no longer accepts that shape, so the helper's
parameter type was tightened to the same two-variant union. No behavior
changed, only the test helper's type.

`npm run check` (typecheck, lint, coverage-gated suite — the coverage script
and thresholds changed concurrently under FEAT-014 while this ticket was in
progress) is green: 1312 tests passing, 0 failing, exit 0, coverage 99.67%
lines / 95.65% branches / 99.80% funcs (thresholds 95/90). Net new/changed
tests from this ticket, across the touched files: `faq-prefill.test.ts` +4,
`open-support-conversation.test.ts` +4, `participant-name.test.ts` +5,
`unread-messages.test.ts` +3,
`activity-timeline.test.ts` +4, `customer-merge-plan.test.ts` favorites tests
rewritten (2 removed, 4 added), `magic-link-status.test.ts` +1,
`ledger-rows.test.ts`/`payout-rows.test.ts`/`seller-accounts.test.ts` +1 each,
`customers.test.ts` +2, `sellers.test.ts` +1, `open-conversation.test.ts` −1
(the now-unrepresentable TypeError case), `conversation-subject.test.ts`
rewritten for the union shape. An exact whole-suite before/after delta was not
captured, since other workers are committing to this same tree concurrently.

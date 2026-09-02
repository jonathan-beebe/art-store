---
id: MAINT-005
type: maintenance
status: resolved
created: 2026-09-01
---

# MAINT-005: Messaging v2 docs refresh and final validation

## Problem
`docs/messaging.md` was written before FEAT-040 … FEAT-043 as the design of record; after the four lanes land it will describe what was intended rather than what shipped. `docs/ontology.md` still lacks the messaging entities (an open item since the first round), `docs/admin.md` and `README.md` describe the one-admin support thread, and `docs/alignment.md` §5 lists the admin messaging rows as "existing".

## Goal
The docs describe the messaging subsystem as it runs on the branch, and one full gate has run on the merged branch before the PR opens.

## Outcome
- [x] `docs/messaging.md` reconciled against the code: every route, scope, action, and rule it names exists under that name; the "Costs stated" section lists what is still deferred.
- [x] `docs/ontology.md` gains the messaging entities; `docs/admin.md`, `docs/architecture.md`'s Sites/messaging mentions, `README.md`, and `docs/alignment.md` §5's admin rows say what is there.
- [x] `make check` (lint → assets → coverage) green once on the merged branch; the test count and coverage are recorded in the journal.

## Why it matters
The docs are how the next round (and the other two prototypes, which owe the same shapes) learns what the PHP messaging subsystem is.

## Related work
- FEAT-040, FEAT-041, FEAT-042, FEAT-043

## Working

Read `docs/messaging.md` against the actual routes (`make routes`), the
`App\Domain\Messaging\*` and `App\Actions\Messaging\*` classes,
`ConversationPolicy`, `Conversation`/`Message` models and scopes, the three
`MessagesQueryRequest`/`ShopMessagesIndexRequest` classes, notifications,
and `ActorDisplay`, cross-checked against the `## Working` sections of
FEAT-040..043.

Doc-vs-code points resolved in the code's favour:
- `docs/messaging.md` § "Who may read, post, and resolve": the oversight
  view's "Message seller"/"Message customer" buttons carry order context
  only on a fulfillment thread — a listing-question oversight thread's
  buttons carry none, since `ThreadOpening::adminSeller()`/`adminCustomer()`
  take no listing id. Previously the doc said both carried "the order or
  listing."
  § "Opening a thread": `OpenConversation` is explicitly `ConversationSubject|ThreadOpening`-typed and is what `OpenThread` calls internally, not a
  fulfillment-only action.
  § "How a thread names its other side": added the storefront's deviation —
  it reads a seller's own `name` rather than `counterpartName()`'s
  shop-name-first read.
  Added a "Follow-ups" list: `Admin::platformAdmin()` unused in production
  code, the `adminSeller()`/`adminCustomer()` no-listing-id gap, the
  `x-seller.list-detail` pinned-footer gap, and the Node/Rails rework owed.
- `docs/ontology.md`: the Conversation/Message/ListingFaq entries described
  the pre-FEAT-040 single-thread, find-or-open, no-title shape; rewritten
  for the four kinds, the desk, title, resolved status, and reply-to. The
  Admin entity's "holds one side of a support Conversation" line rewritten
  for the desk (every admin, collectively) plus oversight reads.
  - `docs/admin.md`: the `/admin/messages` row said "the admin inbox"; now
  names the shared desk, filter/status queues, and oversight read-only.
  The `POST .../messages` (open-thread) row updated for the titled-thread
  shape. Removed a `GET /admin/events` row — that SSE route does not exist
  (the live-badge stream was removed before this branch; unread counts are
  server-rendered). Noted the one exception to the list/detail pattern's
  "show's pane is the index's own default" claim: Messages' `show()`/
  `store()` read `filter=all&status=all` rather than the index's
  `needs-reply`/`open` default.
- `docs/architecture.md`: `ConversationPolicy`'s abilities row was missing
  `resolve`/`reopen`. The "messaging [controllers] scope reads and writes
  by the signed-in admin" line was wrong — the desk's reads span every
  thread; only the write attribution (reply, "handled by", resolve) is
  scoped to the signed-in admin.
- `README.md`: seeded-accounts prose and table predated FEAT-040's richer
  `MessagingSeeder` (5 conversations, not "one of each kind") and its
  second customer, Luna Lovegood (shares an email with the Lovegood
  Curiosities seller row) — rewrote both against the actual seed, verified
  by querying a `make fresh`-seeded dev database for who has which unread
  message. The "## JavaScript" section and the Layout section's
  `public/`/`app/Support/` lines described `live-badge.js` and
  `UnreadCountStream`, both removed before this branch (2026-08-31, "Live
  badge removed" decision); rewrote for `composer.js`.
- `docs/alignment.md` §5: the `/admin/messages` row read "existing"; now
  names the shared desk, the filter/status queues, and the open-thread
  routes. §8's FEAT-040 entry is unchanged, as scoped.

Out of scope, flagged rather than fixed: `docs/architecture.md`'s top
blurb and `docs/review.md` still reference the removed `/events` SSE route
and "the live badge" (predates this branch, not messaging-v2-specific).

`make check` from `<worktree>/prototype/php`: lint green (Pint 1075 files,
PHPStan no errors), assets built, coverage suite **3441 tests passing, 9942
assertions**, **99.6%** line coverage (95% floor). Every under-100% file is
a pre-existing legacy model's unused inverse-side Eloquent relation (an
uncalled `belongsTo`/`hasMany`/`morphMany` accessor — `Admin::conversations()`/
`sentMessages()` and `Seller::sentMessages()` included, both from the first
messaging round, PR #6, untouched here) plus one branch in
`UpdateUnitRequest`'s spec-row loop; none touched by FEAT-040..043 or this
ticket. Full list: `Http/Requests/Seller/UpdateUnitRequest` (97.2%),
`Models/Admin` (75.0%), `Models/Category` (71.4%), `Models/Customer`
(97.4%), `Models/DescriptionSection` (75.0%), `Models/ListingAttribute`
(80.0%), `Models/ListingEvent` (88.2%), `Models/ListingFaq` (85.7%),
`Models/ListingImage` (80.0%), `Models/ListingRemoval` (88.9%),
`Models/Modifier` (94.1%), `Models/ModifierScope` (75.0%),
`Models/OptionAxis` (88.9%), `Models/OptionValue` (92.9%), `Models/Payment`
(90.0%), `Models/Payout` (91.7%), `Models/QuantityBreak` (80.0%),
`Models/Refund` (91.7%), `Models/Seller` (95.7%), `Models/Variant` (97.4%),
`Models/VariantOption` (60.0%).

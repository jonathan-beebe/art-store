---
id: FEAT-017
type: feature
status: resolved
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

## Working

Re-validated: the problem still applied at the start of this ticket. Baseline
`make check` (before any change): 1107 tests passed, 2491 assertions, 0
PHPStan errors, Pint clean — the seven prior tickets already kept the gate
green; this ticket's job was the docs and the coverage command, not the code.

### `make coverage`

`composer test:coverage` ran `pest --coverage` with no `-d memory_limit`, and
the container's 128M default exhausts under the coverage driver. Fixed in
`src/composer.json`: `"test:coverage": ["@php -d memory_limit=1G vendor/bin/pest
--coverage --coverage-html coverage"]`. `make coverage` now completes and
reports **100.0%** of lines under `app/` (confirmed per-file: every listed
class, including the messaging ones, at 100.0%).

### The curl walk

`make fresh` (fresh migrate + seed), then walked the live stack on port 8000
with separate cookie jars per actor.

- **Anonymous ask → seller badge → reply → publish → visible to a second
  visitor.** A cookieless POST to `/art/portrait-of-a-welder/questions`
  minted a `customer_id` cookie and redirected to `/messages/5`. Signed the
  seller (`leo@example.com`, owns that listing) in through the magic-link
  flash; `/seller` rendered `Messages (1)` with `data-events-url=".../seller/events"`.
  Replied on `/seller/messages/5`; the FAQ form pre-filled with
  `source_message_id=13` and the reply text. Published it to
  `/seller/listings/24/faqs`. A **second**, previously-uncookied visitor
  fetching `/art/portrait-of-a-welder` saw the question and answer under
  "Questions & answers" with no ask of their own.
- **Support threads from both sites, admin answers.** `GET /seller/support`
  (signed-in seller) redirected to a fresh `seller.messages.show` thread;
  `GET /support` (the same anonymous shopper) redirected to a fresh
  `shop.messages.show` thread. Both replied. Signed in
  `admin@example.com`; `/admin/messages` showed `Messages (4)` (2 seeded + 2
  new) and both new threads. Admin replied to both; both replies rendered
  back on the seller's and the shopper's thread pages.
- **Non-participant → 404.** The seller's own token against the shopper's
  support thread (`GET /seller/messages/7`), the shopper's against the
  seller's (`GET /messages/6`), and a bogus id (`GET /messages/9999`) all
  answered 404.
- **Blocked customer.** Confirmed via `php artisan tinker` which customer id
  owned the two new threads (id 4), then blocked it through
  `POST /admin/customers/4/blocks` with a reason, over curl with the admin's
  jar. `GET /messages/5` (that customer's jar) rendered the full thread with
  zero `<form method="POST" action=".../messages/5">` reply forms. A
  hand-rolled `POST /messages/5` with the session's `X-XSRF-TOKEN` header
  (the page carried no form token to steal) answered **403 Forbidden** — the
  default `Response::deny()` sentence, no custom copy — and the message count
  on that conversation stayed at 2 (verified via tinker).
- **SSE first frame.** `GET /seller/events` (all caught up) opened with
  `event: unread` / `data: 0`. `GET /admin/events` (two untouched seeded
  support threads) opened with `data: 2` — each stream reads only its own
  actor's count, matching `UnreadCountStreamTest`.

`make check` after the walk: unchanged at 1107 tests / 2491 assertions (the
walk ran against the live container, not the test database).

### Docs

Grepped each doc against `src/app/` per the discovery note rather than
restating what earlier tickets already got right (FEAT-010's Sites/data-model
work and FEAT-016's JavaScript-claim rewrite already held up).

- `docs/README.md` — added a `messaging.md` row; broadened `identity.md`'s
  description to name the admin actor and the messaging tables a merge moves.
- `docs/architecture.md` — intro paragraph now names three sites and the one
  progressive-enhancement script (was "two-sided... no JavaScript required");
  the deployables diagram gained the admin browser and its `EventSource`; "all
  three layouts render `$errors`" and "print it in a debug alert" (was "both
  layouts" — verified against all three `components/layouts/*.blade.php`
  files, which all render `@if ($errors->any())` and `<x-debug-alert />`); the
  Pest-binding list gained `app/Providers` and `app/Support`, which
  `tests/Pest.php` already binds to `Tests\CommerceTestCase` but the doc
  omitted; the Testing section's suite count updated to 1107/2491.
- `docs/identity.md` — new "Admin magic-link sign-in" section (sequence
  diagram + caveats: seeded-only, `SendAdminMagicLinkRequest::admits()`
  gates without revealing which addresses are admins, `SignInAdmin` 404s
  rather than creating a row — verified against `AdminLoginController`,
  `SendAdminMagicLinkRequest`, `SignInAdmin`). The merge sequence and caveats
  now name what moves by blind column write (`CustomerOwnedTables::all()`,
  now five tables including `customer_blocks`) versus what
  `MergeAnonymousCustomer` re-points through a relation (sent messages,
  conversations via `Conversation::moveCustomer()`), with a pointer to
  `docs/messaging.md` § "The merge" for the fold-into-existing-thread case.
  Also fixed a pre-existing gap in the seller sequence diagram
  (`SignInSeller.__invoke` was drawn with no `$now` argument; the method
  takes one).
- `docs/messaging.md` — added two caveats the prior tickets' reviews found but
  never wrote down: the Blade `maxlength="2000"`/`"500"` attributes are
  literals, not reads of `MessageBody::MAX_LENGTH`/`FaqDraft::*_MAX_LENGTH`
  (verified: every messaging view hand-writes the number); and a blocked
  visitor's `shop.listing.questions` submission opens an empty thread because
  `ListingQuestionController` calls `OpenConversation` before
  `authorizeVisitor('post', ...)` (verified against the controller and
  `AskSellerRequest`; confirmed `shop.order.messages` carries no equivalent
  risk since it only opens/finds and redirects). Every route name, scope, and
  method already in the doc checked out against the code — no stale names
  found beyond the two additions above.
- `docs/review.md` — Engineering quality table's file/test counts updated
  (448 files linted, 1107 tests / 2491 assertions). New "Admin site and
  messaging" requirements table (14 rows) mapping the admin actor, blocking,
  the conversation model, authorization, the FAQ path, support threads, the
  nav badge, the live badge, the merge, and seed data to routes and test
  classes — every class name in it confirmed to exist on disk before writing
  it. New "Compared to the Node prototype" section: `subject_key` unique
  index + `firstOrCreate`/`createOrFirst` against Node's unconstrained
  `planConversation` match; `ConversationPolicy` (framework-resolved, three
  call sites) against Node's pure `conversationAccess` predicate (unit-testable,
  unregistered); the polling `UnreadCountStream` (bounded lifetime, holds a
  worker for its whole life) against Node's push-driven `EventEmitter` stream
  (no tick loop, `retry:` hint, but in-process only — Node's own comment says
  a second instance would not share it); form requests + route-model binding
  against Zod route schemas + inline access checks, with the honest read that
  neither is fewer lines for the same guarantee. `/work-*` ticket count
  updated to 30. Known gaps gained four items already documented in
  `docs/messaging.md`: the SSE abort cost, the cookieless `/events` mint, the
  blocked-ask empty thread, and the `maxlength` literal.
- `README.md` — intro names three sites; test count to 1107/2491; the
  repository layout tree gained `Http/Admin`, `View/Composers/`, `Support/`,
  `public/`, the messaging Blade components, and `routes/admin.php`; Known
  gaps gained one line covering the two messaging costs above (pointing at
  `docs/messaging.md` for detail rather than restating it).

### Found, not fixed

- `docs/ontology.md` has no `Conversation`, `Message`, or `Listing FAQ` entity
  — FEAT-011's review recorded this as out of scope for that ticket, and this
  ticket's Outcome does not list `ontology.md`, so it stays as recorded.
- Everything else the prior seven tickets' reviews flagged as "found, not
  fixed" was either already resolved by a later ticket (checked: FEAT-010's
  `BlockCustomer`/`LiftCustomerBlock` domain-`if` note, FEAT-011's
  `ConversationFactory` state-override note, `ConversationPolicy::post`'s
  wordless deny, and the unused `$now` on `UpdateListingFaq`/
  `UnpublishListingFaq` are all still true and still out of scope — none of
  them were on this ticket's Outcome list) or already carried into a doc by
  the ticket that found it (the SSE worker cost and the cookieless mint were
  already in `docs/messaging.md` from FEAT-016; verified rather than
  re-added).

### Verification

`make check` (final): **1107 tests passed, 2491 assertions**, 0 PHPStan
errors, Pint clean over 448 files. `make coverage`: **100.0%** of lines.
`tests/SidecarsTest.php`'s exception list: still empty.

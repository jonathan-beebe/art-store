---
id: IMPRV-027
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-027: messaging refusals are results

## Problem

Messaging and FAQ refusals travel as thrown `TransitionError`s: an invalid
body, a foreign conversation, and a blocked account in
`app/actions/messaging/post-message.ts:33`, `:103`, `:105`, and an
already-published question in
`app/actions/messaging/publish-listing-faq.ts:81`. They are caught in
`app/sites/seller/routes/messages.ts:109`,
`app/sites/shop/routes/messages.ts:192` and `:241`, and
`app/sites/seller/routes/faqs.ts:172`. Each is an expected outcome modeled
as an exception, and the refused log line's reason is a class name.

## Goal

A refused message post or FAQ publish is a normal result with a named reason.

## Outcome

Messaging and FAQ actions answer a refusal with a value naming the reason and
the facts involved; the four catching routes render from the result; `refused`
log lines carry `data.reason`; no messaging or FAQ path throws
`TransitionError`.

## Why it matters

These refusals reach customers and sellers mid-conversation, where the
retry/wait/stop distinction matters most: an invalid body is retryable, a
blocked account is a stop. A named reason is what lets the route choose.

## Related work

- IMPRV-024 — errors carry a reason and data (lands the refusal shape this migration uses)
- IMPRV-025, IMPRV-026 — the same migration for listings and orders

## Working

- 2026-08-25 — baseline pinned: the six affected test files (post-message,
  publish-listing-faq, seller/shop/admin messages routes, seller faqs route)
  run 62/62 green in Docker.
- Throw sites confirmed: `post-message.ts:33` (invalid body, before the story
  opens, so it logs nothing today), `:103` (foreign conversation), `:105`
  (blocked account), `publish-listing-faq.ts:81` (already published). Catch
  sites: `seller/routes/messages.ts:109`, `shop/routes/messages.ts:192` and
  `:241`, `seller/routes/faqs.ts:172`, plus `admin/routes/messages.ts:120`,
  which the ticket's outcome ("no messaging or FAQ path catches
  TransitionError") also covers.
- Reasons: `invalid_body` (data: conversation_id), `foreign_conversation` and
  `account_blocked` (data: conversation_id, sender_type, sender_id),
  `already_published` (data: listing_id, source_message_id, listing_faq_id of
  the row already there).
- Shape follows IMPRV-025/026: `PostMessageResult` = posted | Refusal,
  `PublishListingFaqResult` = published | Refusal; unwrap helpers
  `postedMessage` / `publishedFaq` throw `BrokenContractError` for internal
  callers (seed-messaging, fixtures, tests that use the returned row).
- The three thread routes render the same copy today because it rides the
  thrown error's message; the sentence per reason moves to one exported
  `messagePostRefusalCopy` beside the action so the sites cannot drift.
- `shop/routes/messages.ts` questions route: the open + first post stay one
  transaction; a refused post throws a route-local sentinel carrying the
  refusal so the open rolls back, caught in the same handler.
- Moderation keeps `TransitionError` (IMPRV-028): `admin/routes/moderation.ts`
  and `actions/moderation/*` untouched.
- 2026-08-25 — TDD red: the two action test files rewritten to the result
  contract fail at module load (missing `postedMessage`/`publishedFaq`
  exports), plus a new action-level `already_published` test. Green after the
  migration: 16/16 in the two action files, 63/63 in the four route files with
  the copy/status/redirect assertions untouched.
- Reviewer verdict: accept, no behavior drift. `FirstMessageRefused` is
  module-local with one throw site inside the transaction and one catch in the
  same handler; the blocked-customer question test still proves the opened
  conversation rolls back. Reviewer landed the two coverage nits: unwrap
  helpers throw `BrokenContractError` carrying the reason, and
  `messagePostRefusalCopy` gives the sentence for each reason.
- Final: `make check` green — 2076 tests, 2076 pass; coverage 99.41 lines /
  95.81 branches / 99.46 functions. `TransitionError` remains only in
  `core/`, moderation, and `log-story.test.ts`'s mechanism test.

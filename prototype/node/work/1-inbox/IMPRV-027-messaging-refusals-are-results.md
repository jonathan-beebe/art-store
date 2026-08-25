---
id: IMPRV-027
type: improvement
status: open
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

---
id: RFCTR-006
type: refactor
status: resolved
created: 2026-08-25
---

# RFCTR-006: the machinery tells the refusal's story

## Problem

Every action that can refuse hand-writes the same `ended` branch:
`{ phase: 'refused', msg, data: { reason: result.reason, ...result.data } }`
appears in ~10 actions (e.g. `app/actions/fulfillments/mark-shipped.ts:52`,
`app/actions/listings/change-listing-status.ts`, the messaging and
moderation actions), and each copy is a chance to drop `reason` or the data.
Separately, `mark-shipped.ts:46` logs `status_from: 'awaiting_shipment'` as
a hard-coded literal on its `did` line instead of the status it read — the
same baked-in-fact disease IMPRV-029 cured in user-facing copy, living on in
log data.

## Goal

An action states its `did` story; the machinery tells the refusal's.

## Outcome

`actionStory`/`tellStory` write the `refused` line from a returned refusal
itself — `data.reason` and the refusal's data always present, never
assembled at a call site; an action supplies its `did` mapping and one
refusal sentence only. No action hand-builds a `refused` ending. `did`
lines carry facts that were read, none hard-coded; log output is unchanged
apart from `mark-shipped`'s corrected `status_from`.

## Why it matters

The stated goal of the pattern is that implementing and supporting it is
easy. The refused ending is pure ceremony today — ten copies of the same
object literal — and the one invariant that matters (`reason` always on the
line) is enforced by diligence instead of by the machinery.

## Discovery notes

Advisory: the machinery can detect `result.outcome === 'refused'`
structurally, the way `mustSucceed`-style helpers do; the story type then
needs only the success ending and a refusal message (a sentence or a
per-reason map). Sweep every action's `ended` while there, checking `did`
data for other baked-in facts. Depends on RFCTR-005's typed data landing
first so the derived line types cleanly.

## Related work

- RFCTR-005 — typed refusal data (land first)
- IMPRV-025..028 — the actions carrying the copied ending
- 93e095d (IMPRV-029) — the copy-side precedent for un-baking facts

## Working

2026-08-25 — survey. The copied ending lives in 12 stories: mark-shipped,
confirm-delivered, decline-fulfillment, issue-refund, cancel-order,
change-listing-status, post-message, publish-listing-faq, remove-listing,
lift-listing-removal, block-customer, lift-customer-block. A 13th,
sign-in-with-magic-link, hand-derives `reason` from a bespoke result shape
and converts to `Refusal` arms byte-identically. Three stories stay
hand-written because their results carry no refusal shape and their refused
lines carry no top-level `reason`: place-order (`unavailable` lines),
finalize-order (`decline_reason`), record-listing-view (`null` collapse).

Machinery shape: `Story<Result>` gains `refusedMsg?: string`, `ended`
narrows to `Told<Result> = Exclude<Result, Refusal>`, and a conditional
intersection makes `refusedMsg` required whenever the result union carries a
refusal arm. `tellStory` reads the refusal structurally (`outcome ===
'refused'`, string `reason`) and writes `{reason, ...data}` itself; a
refusal that ends a story with no sentence throws
`BrokenContractError('unended_refusal')`. The `told` type predicate
(`result is Told<Result>`) compiles without a cast.

Baked did-facts found in the sweep: mark-shipped `status_from:
'awaiting_shipment'`, confirm-delivered `status_from: 'shipped'`,
decline-fulfillment `status_from: 'awaiting_shipment'` — each now logs the
status the row held when read (mark-shipped and confirm-delivered via an
internal story result carrying `statusFrom`, decline via `statusFrom` on
`IssueRefundResult`'s issued arm). change-listing-status refusal data gains
`seller_id` so the derived line keeps today's bytes.

2026-08-25 — resolved. The 12 copied endings and sign-in's bespoke arm are
gone; each story supplies `refusedMsg` and a did mapping only (mark-shipped's
story spec: 30 lines → 19). `make check` green: 2088 tests, coverage
99.33/95.64/99.53 against the 95/90 gate. Reviewer verdict: accept, no
defects; noted the `Shipping`/`Delivery` internal-result shape repeats in
two files — a shared helper earns its place at a third use, not at two.

---
id: RFCTR-007
type: refactor
status: resolved
created: 2026-08-25
---

# RFCTR-007: one generic unwrap for refusable results

## Problem

Eight per-action unwrappers repeat the same five lines — check
`outcome === 'refused'`, throw
`BrokenContractError(reason, sentence, data)`, otherwise return the success
value: `shippedFulfillment` (`app/actions/fulfillments/mark-shipped.ts:101`),
`deliveredFulfillment`, `cancelledOrder`, `changedListing`, `postedMessage`,
`publishedFaq`, and the four moderation unwrappers. The repo's own rule
reserves abstraction for duplication felt three times; this one has been
felt eight.

## Goal

Refusal-to-defect unwrapping is written once.

## Outcome

A single generic helper returns the success arm of a refusable result and
throws `BrokenContractError` carrying the refusal's reason and data when it
is refused; internal callers (seeds, fixtures, sweep, checkout) use it; the
per-action unwrappers are deleted or reduced to one-line aliases where a
name still earns its place. Behavior and log output identical.

## Why it matters

Every new refusable action currently ships another copy of the unwrapper,
and each copy words its own defect message — support cost with no
information gain. One helper makes the pattern one line to adopt.

## Discovery notes

Advisory: `mustSucceed(result, message?)` beside `Refusal` in
`app/core/refusal.ts`, typed to return `Exclude<Result, Refusal>` so the
success arm narrows without assertions. The transition-table unwrappers
(`orderMovedTo`, `fulfillmentMovedTo`, `listingMovedTo`) wrap a call as well
as an unwrap — they may keep their names on top of the helper. Sequenced
after RFCTR-006 since both touch the actions.

## Related work

- RFCTR-005, RFCTR-006 — the same support-cost sweep
- IMPRV-025..028 — where the unwrappers accumulated

## Working

- 2026-08-25 — re-validated: ten per-action unwrappers exist (mark-shipped:105,
  confirm-delivered:105, cancel-order:86, change-listing-status:117,
  post-message:139, publish-listing-faq:101, remove-listing:88,
  lift-listing-removal:67, block-customer:83, lift-customer-block:52), each the
  same check/throw/return shape. Three transition unwrappers (orderMovedTo,
  fulfillmentMovedTo, listingMovedTo) wrap a call plus the unwrap.
- No test pins a per-action defect message (grep "was refused:" in *.test.ts is
  empty); tests pin `error.reason` and sometimes `error.data`. The transition
  sentences ARE pinned (order-status.test.ts:100, fulfillment-status.test.ts:94,
  order-lifecycle.test.ts:193, finalize-order.test.ts:170/187,
  seller/routes/listings.test.ts:541) — those keep their bytes via the sentence
  each *MovedTo passes to the helper.
- Plan: `mustSucceed(result, message?)` in core/refusal.ts returning
  `Exclude<Result, Refusal>`; structural refusal detection (`refusalOf`) moves
  from log-story.ts to core/refusal.ts and log-story imports it. The ten
  per-action unwrappers are deleted; call sites read the success field off
  `mustSucceed(...)`. The three *MovedTo keep their names on the helper.

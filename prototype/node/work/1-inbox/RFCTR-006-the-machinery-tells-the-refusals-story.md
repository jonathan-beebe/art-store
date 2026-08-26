---
id: RFCTR-006
type: refactor
status: open
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

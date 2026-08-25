---
id: IMPRV-028
type: improvement
status: open
created: 2026-08-25
---

# IMPRV-028: moderation refusals retire TransitionError

## Problem

Moderation refusals travel as thrown `TransitionError`s: already-removed and
not-removed listings in `app/actions/moderation/remove-listing.ts:52` and
`lift-listing-removal.ts:38`, a permanent removal in
`lift-listing-removal.ts:40`, and already-blocked / not-blocked customers in
`block-customer.ts:48` and `lift-customer-block.ts:29`. No admin route
catches them, so they surface as `failed` lines and error pages for outcomes
an admin can reach with a stale tab. These are the last `TransitionError`
sites once IMPRV-025 through IMPRV-027 land.

## Goal

A refused moderation change is a normal result with a named reason, and the
throw-a-refusal pattern is gone from the codebase.

## Outcome

Moderation actions answer an already-done or impossible change with a refusal
value naming the reason; the admin routes render the refusal; `refused` log
lines carry `data.reason`; no code constructs `TransitionError`, and
`core/transition-error.ts` and `isDomainRefusal`
(`app/core/logging/logged-error.ts`) are retired or reduced to what remains
in use.

## Why it matters

A moderator working from a stale list sees a defined flow ("already removed")
in place of an error page, and once the last throw-site is gone the rule is
simple to hold: a throw is a defect, always.

## Related work

- IMPRV-024 — errors carry a reason and data (lands the refusal shape this migration uses)
- IMPRV-025, IMPRV-026, IMPRV-027 — the same migration for listings, orders, and messaging

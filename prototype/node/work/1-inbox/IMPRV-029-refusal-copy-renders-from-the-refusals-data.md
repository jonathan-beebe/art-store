---
id: IMPRV-029
type: improvement
status: open
created: 2026-08-25
---

# IMPRV-029: refusal copy renders from the refusal's data

## Problem

Routes migrated by IMPRV-025 and IMPRV-026 interpolate entity status into
refusal copy from the row they fetched before calling the action —
`app/sites/seller/routes/listings.ts` renders
`A listing cannot move from ${listing.status} to ${status}.` off the
pre-action read, and the order/fulfillment routes follow the same template —
while the refusal's `data.status_from` holds the status the action read
inside its transaction. Under a concurrent move the flash names a status the
refusal did not see. Both tickets recorded this in their Working sections as
a pattern-level follow-up.

## Goal

Refusal copy states the facts the refusal itself carries.

## Outcome

Every route that renders a refusal builds its sentence from the refusal's
`data`; no route reads entity state fetched before the action to word a
refusal. The copy under a race names the status the action refused on.

## Why it matters

The migration's point is one source of truth for what happened: the refusal
carries reason and facts so the log line and the page tell the same story.
A route holding a second, earlier read can contradict the log during a race,
which is exactly when someone reads both.

## Discovery notes

Advisory: IMPRV-027's `messagePostRefusalCopy` — one exported mapper beside
the action, taking the refusal — is a shape that keeps sites from drifting.
Messaging copy interpolates no entity state, so it likely already conforms;
the listing and order/fulfillment/refund routes are the sites to sweep, and
moderation (IMPRV-028) should land conforming.

## Related work

- IMPRV-025 (098e871), IMPRV-026 (d451322) — the routes carrying the snapshot pattern
- IMPRV-027 (b3fc42c) — the copy-mapper shape
- IMPRV-028 — moderation lands after this is filed; its routes should conform

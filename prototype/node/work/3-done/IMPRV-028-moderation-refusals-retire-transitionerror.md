---
id: IMPRV-028
type: improvement
status: resolved
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

## Working

- 2026-08-25 — re-validated on branch `node/errors` at `ded5ab4`. The five
  throw sites stand as filed: `remove-listing.ts:52`, `lift-listing-removal.ts:38`
  and `:40`, `block-customer.ts:48`, `lift-customer-block.ts:29`. One caller
  catches them: the admin `moderationRoute` (`sites/admin/routes/moderation.ts:75`)
  catches `TransitionError` and renders `error.message` — raw prefixed-id prose —
  as the flash alert. The migration keeps the 302 + flash idiom and replaces the
  copy with per-reason sentences rendered from the refusal (IMPRV-029-conformant).
- Reasons chosen: `already_removed` (remove), `not_removed` / `permanent_removal`
  (lift removal), `already_blocked` (block), `not_blocked` (lift block). Data
  carries `listing_id` / `customer_id` plus the active row's id where one exists.
- Result shapes follow IMPRV-025..027: `{outcome:'removed'|'lifted'|'blocked'}`
  unions with `Refusal<Reason>`, `ended` maps refusal → `refused` line with
  `data.reason`, unwrap helpers (`removedListing`, `liftedListingRemoval`,
  `blockedCustomer`, `liftedCustomerBlock`) throw `BrokenContractError` for
  internal callers (seed-catalog, seed-customers, test setups).
- Retirement: delete `core/transition-error.ts` (+ test), remove
  `isDomainRefusal` and the thrown-refusal branch from `logged-error.ts` /
  `log-story.ts` (`logException`); a throw always logs `failed`.
  `docs/alignment.md` §2.2 stays — php/rails still model refusal as a thrown
  class.
- Landed: the four actions return result unions with the reasons above; the
  admin `moderationRoute` branches on `outcome === 'refused'` and renders
  `moderationRefusalCopy(result.reason)` as a flash alert on the same 302
  ("This listing is already removed." / "This listing is not removed." /
  "A permanent removal cannot be lifted." / "This customer is already
  blocked." / "This customer is not blocked."); `ModerationRefusalReason` is
  derived from the actions' result types via a distributive `ReasonOf` infer.
  Retired: `core/transition-error.ts` (+ test), `isDomainRefusal`, and
  `logException`'s thrown-refusal branch — a throw always logs `failed`.
  TDD: tests rewritten first (4 action suites + moderation route red; 14 setup
  callers wrap via the new unwrap helpers), then implementation to green.
  `make check` green: 2069 tests, 0 fail; coverage 99.32 lines / 95.71
  branches / 99.46 functions. `grep TransitionError|transition-error|
  isDomainRefusal` over `src/` — zero matches. Reviewer: accept, no defects.

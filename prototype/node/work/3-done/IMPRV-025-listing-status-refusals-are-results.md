---
id: IMPRV-025
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-025: listing status refusals are results

## Problem

An illegal listing status move travels as a thrown `TransitionError`
(`app/core/listings/listing-status.ts:25`,
`app/actions/listings/change-listing-status.ts:72`) and is caught in
`app/sites/seller/routes/listings.ts:373`. A stale form or a
no-longer-possible move is an expected outcome (`docs/principles.md`), yet it
is modeled as an exception, and the refused log line's reason is a class
name.

## Goal

A refused listing status change is a normal result with a named reason.

## Outcome

Listing status actions answer an illegal move with a refusal value naming the
reason and the facts (`status_from`, `status_to`); the route renders the
refusal from the result it was returned; the `refused` log line carries
`data.reason`; no listing path throws `TransitionError`.

## Why it matters

The return/throw rule: a refusal is the answer the person gets, and a throw
is a defect. While refusals travel as exceptions, a real defect on the same
path can be swallowed by the same catch, and the route cannot tell a stale
form from a bug.

## Related work

- IMPRV-024 — errors carry a reason and data (lands the refusal shape this migration uses)
- 2d44906 — docs: log contract gains emoji prefixes, refusal reasons, and error reason/data

## Working

- 2026-08-25 — re-validated: `listing-status.ts:25` throws `TransitionError`,
  `change-listing-status.ts:72` throws for a removed listing,
  `sites/seller/routes/listings.ts:373` catches and renders. Baseline: 65
  tests green across `listing-status`, `listing-stock`,
  `change-listing-status`, and `sites/seller/routes/listings` test files.
- Internal callers found: `core/listings/listing-stock.ts` calls
  `transitionListing` (both moves statically legal), and
  `sites/seller/test-fixtures.ts`, `sites/shop/storefront-fixtures.ts`,
  `db/seed-wizarding-sellers.ts`, `db/seed-catalog.ts` call
  `changeListingStatus` for legal moves. These get the Defect (`BrokenContractError`)
  unwrap; the route branches on the returned value.
- Reasons named: `illegal_transition` (with `status_from`/`status_to`) and
  `listing_removed`.
- Out of scope, untouched: `core/transition-error.ts`, `logged-error.ts`
  refusal detection, orders/fulfillments/messaging/moderation throw sites
  (IMPRV-026..028), `log-story.test.ts` machinery tests.
- 2026-08-25 — resolved: `transitionListing` returns
  `{ outcome: 'allowed', status } | Refusal<'illegal_transition'>`;
  `changeListingStatus` returns
  `{ outcome: 'changed', listing } | Refusal<'illegal_transition' | 'listing_removed'>`
  with the refused log line written by the story's `ended` mapping
  (`data.reason` + `listing_id`/`status_from`/`status_to`). Internal callers
  unwrap through `listingMovedTo` / `changedListing`, which throw
  `BrokenContractError`. Route UX unchanged (302 + same flash copy), route
  test file untouched. `make check`: 2064 tests, 0 fail; coverage 99.39%
  lines / 95.66% branches.

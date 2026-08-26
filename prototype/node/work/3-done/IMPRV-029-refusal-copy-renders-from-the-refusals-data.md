---
id: IMPRV-029
type: improvement
status: resolved
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

## Working

2026-08-25 — survey of every refusal-rendering site:

Non-conforming (copy interpolates a pre-action row read):

- `app/sites/seller/routes/listings.ts:372` — `${listing.status}` from `findOwnedListing`
- `app/sites/seller/routes/orders.ts:199` (ship) — `${owned.fulfillment.status}`
- `app/sites/seller/routes/orders.ts:235` (decline) — `${owned.fulfillment.status}`
- `app/sites/shop/routes/fulfillments.ts:30` (delivered) — `${fulfillment.status}`
- `app/sites/admin/routes/orders.ts:86` (cancel) — `${order.status}`
- `app/sites/admin/routes/fulfillments.ts:98` (refund) — `${found.status}`

Conforming (verified): `messagePostRefusalCopy` (reason + resubmitted form body,
no entity state), `moderationRefusalCopy` (static sentence per reason),
`app/sites/shop/routes/orders.ts:30` (refusal renders not-found, no copy), auth
sign-in (static copy).

Every needed fact is already in the refusals' data: `transitionFulfillment`,
`transitionOrder`, `planRefund`, and `changeListingStatus` all put
`status_from`/`status_to` in `data` (`order_unpaid` carries no statuses and its
sentence is static). No action refusal data to extend.

Plan:

- `app/core/refusal.ts` gains a narrowing helper that reads
  `status_from`/`status_to` off a refusal's data and throws
  `BrokenContractError` when a refusal arrives without them.
- Sentence lives once per shape: `fulfillmentTransitionRefusalCopy` beside
  `transitionFulfillment`, `orderTransitionRefusalCopy` beside
  `transitionOrder` (both reused for the `movedTo` unwrappers' messages),
  `listingStatusRefusalCopy` beside `changeListingStatus`,
  `refundRefusalCopy` beside `issueRefund` (serves the seller decline and
  admin refund sites; delegates its illegal_transition branch to the
  fulfillment sentence).
- The six routes call the mappers with the refusal; no route reads entity
  state to word a refusal. Non-race bytes identical (`status_to` in data is
  the same literal each route hard-coded).
- Race pin: an HTTP-level interleave has no seam (routes import actions
  directly, both reads hit the same db in one request), so the race is pinned
  at the mapper: a refusal whose `status_from` differs from any earlier read
  renders the refusal's status. Route tests pin the wiring (flash ==
  mapper(refusal)).

Landed:

- `transitionFacts(refusal)` in `app/core/refusal.ts` — narrows
  `status_from`/`status_to` off the data, throws
  `BrokenContractError('missing_transition_statuses', …)` otherwise.
- `fulfillmentTransitionRefusalCopy` (`core/orders/fulfillment-status.ts`,
  reused by `fulfillmentMovedTo`'s message), `orderTransitionRefusalCopy`
  (`core/orders/order-status.ts`, reused by `orderMovedTo`),
  `listingStatusRefusalCopy` (`actions/listings/change-listing-status.ts`),
  `refundRefusalCopy` (`actions/refunds/issue-refund.ts`, delegates its
  illegal_transition branch to the fulfillment sentence).
- The six routes call the mappers with the refusal; no route reads entity
  state to word a refusal. Non-race bytes verified identical (each action's
  `data.status_to` is the literal the route hard-coded).
- Reviewer: accept-with-nits, both nits landed — `fulfillmentTransitionRefusalCopy`
  takes a bare `Refusal` (its one fact source is `transitionFacts`), and
  `orderTransitionRefusalCopy`/`refundRefusalCopy` gained the same
  stale-status negative race tests the other two mappers had.
- `core/listings/listing-status.ts:44` keeps its inline sentence in
  `listingMovedTo`: `listingStatusRefusalCopy` lives in the actions layer
  (it also covers `listing_removed`), and core does not import actions.
- TDD red (5 files fail at load) → green; 2076 → 2083 tests, `make check`
  green, coverage 99.33/95.75/99.46 against the 95/90 gate.

---
id: IMPRV-026
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-026: order and fulfillment refusals are results

## Problem

Illegal order and fulfillment status moves and blocked refunds travel as
thrown `TransitionError`s (`app/core/orders/order-status.ts:48`,
`app/core/orders/fulfillment-status.ts:36`,
`app/actions/refunds/issue-refund.ts:83`), caught in
`app/sites/seller/routes/orders.ts:193` and `:231`. These are expected
outcomes — a stale form, a fulfillment already moved on, a refund already
issued — modeled as exceptions, and the refused log line's reason is a class
name.

## Goal

A refused order, fulfillment, or refund change is a normal result with a
named reason.

## Outcome

Order, fulfillment, and refund actions answer a blocked change with a refusal
value naming the reason and the facts (`status_from`, `status_to`, the ids
involved); routes render the refusal from the result; `refused` log lines
carry `data.reason`; none of these paths throw `TransitionError`.

## Why it matters

Order state is where money moves. A route that can tell a stale form from a
defect renders the right flow (retry, wait, or stop) and a defect on the same
path stays loud.

## Related work

- IMPRV-024 — errors carry a reason and data (lands the refusal shape this migration uses)
- IMPRV-025 — listing status refusals are results (same migration, listings)

## Working

- 2026-08-25 — re-validated: the three throw sites stand as filed
  (`order-status.ts:48`, `fulfillment-status.ts:36`, `issue-refund.ts:83`);
  catch sites at `seller/routes/orders.ts:193`/`:231` plus
  `shop/routes/fulfillments.ts:32`, `admin/routes/orders.ts:88`,
  `admin/routes/fulfillments.ts:97`. Baseline suite pinned green: 2064 pass.
- Migration follows the IMPRV-025 shape (commit 098e871): transition tables
  return `{outcome:'allowed'} | Refusal`, actions return result unions, the
  story's `ended` maps a refusal to the refused line with `data.reason`,
  routes branch on outcome with byte-identical copy, and internal callers
  unwrap via `*MovedTo`/`cancelledOrder`/`shippedFulfillment`/
  `deliveredFulfillment`, which throw `BrokenContractError`.
- Reasons: `illegal_transition` with `{status_from, status_to}` for table
  refusals; `planRefund` distinguishes `order_unpaid` (no approved payment)
  from `illegal_transition`.
- Internal/defect paths: `payment-attempt.ts` (checkout/payment), the
  stale-order sweep, seed-order-history, seller test fixtures.
- Shop customer cancel keeps its `isCancellable` pre-guard; a refusal from
  the result answers the same 404 the guard answers.
- Messaging and moderation keep `TransitionError` (IMPRV-027/028).
- Delivered: `transitionOrder`/`transitionFulfillment` return
  `{outcome:'allowed', status} | Refusal<'illegal_transition'>` with
  `orderMovedTo`/`fulfillmentMovedTo` unwrappers; `planRefund` returns
  `{outcome:'planned', intent} | Refusal<'order_unpaid'|'illegal_transition'>`;
  `markShipped`/`confirmDelivered`/`cancelOrder`/`cancelOrderAsAdmin`/
  `issueRefund`/`declineFulfillment` return result unions with
  `shippedFulfillment`/`deliveredFulfillment`/`cancelledOrder` unwrappers;
  five routes branch on outcome with byte-identical copy, codes, and
  redirects; refused log lines carry `data.reason` plus the ids and
  `status_from`/`status_to`.
- TDD: 10 test files updated red-first (65 failures against the old
  implementation), then green. Reviewer verdict: accept with nits; the two
  test-coverage nits (world-unchanged assertions on the refund refusal
  cases, direct `cancelledOrder`/`deliveredFulfillment` unwrapper tests)
  were added. 2064 → 2072 tests, 0 fail; `make check` green, coverage
  99.36/95.70/99.46 (gate 95/90).
- Race copy note, inherited from the IMPRV-025 template: route refusal
  messages read the pre-action status snapshot rather than the refusal's
  `data.status_from`; the shop customer-cancel race now answers the
  pre-guard's 404 where an uncaught throw answered 500.

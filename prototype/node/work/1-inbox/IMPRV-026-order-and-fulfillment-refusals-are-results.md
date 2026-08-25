---
id: IMPRV-026
type: improvement
status: open
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

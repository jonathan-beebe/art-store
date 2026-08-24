---
id: FEAT-019
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-019: Order lifecycle back half — cancel, stale sweep, seller decline, admin refund

## Problem
`ORDER_STATUSES` includes `cancelled` and the customer can cancel an unpaid order, but nothing sweeps stale `pending_verification` orders (they hold stock forever), a seller cannot decline a fulfillment, an admin cannot cancel or refund, `LEDGER_ENTRY_TYPES` has no reversal, and there is no `refunds` table. `docs/alignment.md` §4 fixes the shared state machines, the `refunds` row, the `refunded` ledger entry and its three timings, the sweep, and the sad-path list.

## Goal
The whole money-and-stock lifecycle of an order — happy and sad — is reachable, refused where it must be, and visible on every site.

## Outcome
The order and fulfillment state machines in §4.1 are enforced (DB CHECK constraints for the new statuses, transitions in the core); `make sweep` cancels stale `pending_verification` orders older than `STALE_ORDER_HOURS`; a seller can decline an `awaiting_shipment` fulfillment with a reason and stock returns; an admin can cancel an unpaid order and refund any fulfillment with a reason; every refund writes a `refunds` row and a `refunded` ledger entry so the balance fold matches §4.2 in all three timings; every sad path in §4.3 has a test; the surfaces in §4.4 exist on the storefront, seller portal, and admin site; the counterpart is notified; `docs/orders.md` and `docs/escrow.md` show the new diagrams.

## Why it matters
Refunds and declines are the half of commerce that decides whether sellers trust the platform and whether the ledger can ever be reconciled; the prototypes compete on exactly this.

## Discovery notes
Node already has `planOrderPlacement`, `stockChangeBetween`, and the customer cancel — the decline and refund are plans of the same shape (pure decision → applied in one `BEGIN IMMEDIATE` transaction). The roll-up of order status from live fulfillments is one pure function. The sweep is a CLI like `run-payouts.ts` with `main(argv, env)`. Admin order/fulfillment detail pages are BUG-007's territory; land that first.

## Related work
- docs/alignment.md §4
- BUG-007
- RFCTR-001 (escrow core)

## Working

### What landed

**States.** `FULFILLMENT_STATUSES` gains `declined` and `refunded`;
`ORDER_STATUSES` gains `refunded`; `LEDGER_ENTRY_TYPES` gains `refunded`. The
transition tables carry the edges §4.1 draws — `awaiting_shipment → declined`,
`{awaiting_shipment, shipped, delivered} → refunded`, and nothing out of either
reversed state. `isReversed(status)` is the new predicate, beside `hasDeparted`.
All three CHECK constraints are regenerated from the same `as const` arrays; the
`orders` and `escrow` migrations were rewritten in place, as §1 allows.

**Rows.** A new `20260823110000-create-refunds.ts` creates `refunds` with the
columns §4.1 names, plus a unique index on `fulfillment_id` (one reversal per
fulfillment) and an index on `(order_id, created_at)`. `orders.refunded_cents`
is added to the orders migration with a default of 0. `commerce-schema.ts` and
the `schema-fidelity.test.ts` samples follow, including a CHECK test for
`refunds.issued_by_type`.

**Core (pure, unit-tested with literals).**

| Function | Path | Signature |
| --- | --- | --- |
| `isReversed` | `core/orders/fulfillment-status.ts` | `(status: FulfillmentStatus) => boolean` |
| `orderStatusFromFulfillments` | `core/orders/order-status.ts` | `(statuses: readonly FulfillmentStatus[]) => OrderStatus` — extended to drop reversed halves |
| `planRefund` | `core/orders/refund.ts` | `(subject: RefundSubject) => RefundPlan` |
| `parseRefundReason` | `core/orders/refund.ts` | `(input: string \| null \| undefined) => RefundReasonResult` |
| `refundMovement` | `core/escrow/ledger-movement.ts` | `(netCents: Cents) => LedgerMovement` |
| `ledgerBalance` | `core/escrow/ledger-balance.ts` | `(movements: readonly BalanceMovement[]) => LedgerBalance` — rewritten as a single walk |
| `feeTotals` | `core/escrow/fee-totals.ts` | `(subjects: readonly FeeSubject[]) => FeeTotals` |
| `staleOrderCutoff` | `core/orders/stale-order.ts` | `(now: Date, staleHours: number) => Date` |

**Actions.**

- `actions/refunds/issue-refund.ts` — the one write both reversals share.
  Reads the fulfillment and the approved payment, calls `planRefund`, and inside
  the same transaction updates the fulfillment, writes the `refunds` row, writes
  the `refunded` ledger entry, moves stock, rolls the order up, rewrites
  `orders.refunded_cents`, and notifies. Story: `refund.issue`.
- `actions/fulfillments/decline-fulfillment.ts` — the seller's half of the
  story (`fulfillment.decline`), wrapping `issueRefund` in the same unit of work
  so both lines carry one `txn_id`.
- `actions/orders/cancel-order-as-admin.ts` — `cancelOrder` plus a notification
  to the customer and to every seller on the order.
- `actions/orders/sweep-stale-orders.ts` + `cli/sweep-stale-orders.ts` —
  `main(argv, env, logger?)` copying `run-payouts.ts`, `--as-of` through the
  shared `parseAsOf`. `npm run sweep` and the real `make sweep` target replace
  the Makefile stub.
- `moveOrderStock` takes an optional `{ sellerId }` scope, which is how a
  decline restores only its own seller's lines.

**Surfaces.** Seller decline form on `/seller/orders/:id` beside Ship, and a
reason/amount panel once declined; `refunded` movements on `/seller/earnings`
(new Movements table over the seller's ledger entries). Customer order page
shows a refund panel with the reason and the amount per reversed fulfillment.
Admin: Cancel form on an unpaid order, a per-fulfillment Refund form on the
order page (carrying `redirect_to`), a Refund form and refund summary on the
fulfillment page, and a Refunds table on the order. `refunded` / `declined`
filter values on `/admin/orders`, `/admin/fulfillments`, and `/admin/ledger`
come free — those selects are built from the unions. `/admin` and
`/admin/accounting` gain `feesRefundedCents` and `refundedCents`, and the
accounting table gains a per-seller Refunded column.

### The §4.3 sad paths and the test that covers each

| Sad path | Test |
| --- | --- |
| Pay a cancelled / refunded / already-paid order | `core/orders/order-status.test.ts` — `a cancelled order cannot be paid`, `an unpaid order is cancelled rather than refunded`, `a paid order cannot be paid twice`; `awaitsCard` reads the same table |
| Cancel a paid order | `sites/shop/routes/orders.test.ts` — `a paid order can no longer be cancelled`; `sites/admin/routes/orders.test.ts` — `POST /admin/orders/:id/cancel refuses a paid order` |
| Decline after ship | `core/orders/refund.test.ts` — `a seller cannot decline after shipping`; `actions/refunds/issue-refund.test.ts` — same name; `sites/seller/routes/orders.test.ts` — `declining after shipping is refused rather than applied` |
| Ship after decline | `core/orders/fulfillment-status.test.ts` — `transition refuses a ship after a decline`; `actions/refunds/issue-refund.test.ts` — `a seller cannot ship after declining` |
| Refund twice | `actions/refunds/issue-refund.test.ts` — `a fulfillment cannot be refunded twice`, `a declined fulfillment cannot then be refunded`; `sites/admin/routes/fulfillments.test.ts` — `refuses a second refund` |
| Refund an unpaid order's fulfillment | `core/orders/refund.test.ts` — `an unpaid order has nothing to refund`; `actions/refunds/issue-refund.test.ts` — `an unpaid order has no fulfillment to refund` |
| Seller declines another seller's fulfillment → 404 | `sites/seller/routes/orders.test.ts` — `declining another seller's order is not found` |
| Customer cancels another customer's order → 404 | `sites/shop/routes/orders.test.ts` — `someone else's order is not found` (pre-existing) |
| Sweep never touches `awaiting_payment` or anything younger than the cutoff | `actions/orders/sweep-stale-orders.test.ts` — `it never touches an order that is only awaiting payment`, `it leaves an order younger than the cutoff alone`, `an order placed exactly at the cutoff is not yet stale`; `cli/sweep-stale-orders.test.ts` — `main leaves an order that is only awaiting payment alone` |
| Stock: decline restores exactly, `sold → for_sale`, admin refund restores nothing | `actions/refunds/issue-refund.test.ts` — `a decline hands exactly the declined quantities back to the storefront`, `a decline leaves the other seller's stock where it is`, `an admin refund restores nothing`, `an admin refunds a fulfillment that has not shipped` |
| Balance fold in all three timings | `core/escrow/ledger-balance.test.ts` and `actions/refunds/issue-refund.test.ts` (below) |

### The §4.2 fold, asserted

Net is 40,500 on a 45,000 subtotal.

| Timing | Entries | held | available | paid out |
| --- | --- | --- | --- | --- |
| Before release | `held +40500`, `refunded −40500` | 0 | 0 | 0 |
| After release | `held +40500`, `released +40500`, `refunded −40500` | 0 | 0 | 0 |
| After payout | `+ paid_out −40500`, `refunded −40500` | 0 | −40,500 | 40,500 |
| Negative carried, later sale of 9,000 released | | 0 | −31,500 | 40,500 |
| Payout of a negative balance | | no `paid_out` row written; `runWeeklyPayout` returns `[]` | | |

### Decisions

- **The fold needs the fulfillment.** A `refunded` entry has to come out of
  `held` or out of `available` depending on whether that fulfillment ever
  released, and no plain per-type total can tell the two apart — before-release
  and after-release produce the same three totals in a different order. So
  `ledgerBalance` now takes `BalanceMovement = LedgerMovement & { fulfillmentId }`
  and walks the movements once instead of summing by type. `ledgerMovements`
  selects the extra column; `ledgerRows` already had it.
- **`planRefund` returns a discriminated union rather than throwing**, following
  `planOrderPlacement` rather than `transitionOrder`. The action turns
  `{ ok: false, refusal }` into a `TransitionError`, so every route's existing
  catch and every story's `refused` phase work unchanged.
- **The intent carries the `paymentId`.** `RefundSubject.paymentId` is
  `PaymentId | null` and `RefundIntent.paymentId` is `PaymentId`, so the shell
  cannot write a `refunds` row without a charge behind it and needs no
  re-narrowing after the plan comes back.
- **The issuer decides the reversal.** `REVERSALS` maps `seller → declined,
  restore` and `admin → refunded, keep`, so a seller-issued refund landing in
  `refunded`, or an admin refund restoring stock, is unrepresentable rather than
  guarded.
- **Decline emits two events, not one.** `fulfillment.decline` wraps
  `refund.issue`; §2.3 lists both and joining the transaction gives them one
  `txn_id`.
- **`orders.refunded_cents` is rewritten from the rows**, not incremented, so it
  cannot drift from the `refunds` table.
- **`holdsStock('refunded')` stays true.** An order reaches `refunded` only
  after each fulfillment has already settled its own stock, so the order-level
  stock change must not fire again.
- **A unique index on `refunds.fulfillment_id`** backs the state machine's
  "refuse the second reversal" where two writers could race it.
- **Admin cancel takes a reason** even though the contract only requires one for
  refunds: the notification §4.4 asks for has to say something, and the same
  `parseRefundReason` covers both forms.

### Deliberately left out

- **No partial refunds.** §4.1 fixes the amount at the whole fulfillment
  subtotal, so there is no amount field on any form.
- **No `declined_at` / `refunded_at` column on `fulfillments`.** `refunds.created_at`
  carries the timing and the contract's row shape does not name one.
- **No refunds list page on the admin site.** §5's table puts refunds on the
  order detail page, which is where they are.
- **`rate_limit.exceed` stays unemitted** — that is §3's ticket, not this one.

### Deviations from the contract

None. Nothing was cut.

### Verification

`make check` green: 1741 tests (from 1631), 99.52% lines / 97.29% branches /
99.54% functions (from 99.53 / 97.34 / 99.51). `make smoke`, `make routes`,
`make sweep`, and `make docs-check` all pass.

### Fix-up

FEAT-019 review found `canRefund` computed on both admin pages as
`canTransitionFulfillment(status, 'refunded')` alone, with no check for an
approved payment behind the order — an unpaid order's fulfillment (created at
placement in `awaiting_shipment`) rendered a working-looking Refund form even
though `issueRefund` would refuse it.

Added `canRefundFulfillment` to `core/orders/refund.ts`, a pure predicate
mirroring the same two gates `planRefund` enforces for an admin's reversal
(`paymentId !== null` and `canTransitionFulfillment(status, 'refunded')`), so
neither query module nor the view restates the rule. `sites/admin/queries/
order-detail.ts` now reads the order's latest approved payment out of the
payments it already fetches and passes it through per fulfillment row;
`sites/admin/queries/fulfillment-detail.ts` runs the same approved-payment
lookup `issue-refund.ts` uses and adds `canRefund` to `FulfillmentDetail`, so
`routes/fulfillments.ts` no longer recomputes it. No view changed — both
already gated on `canRefund`.

Added route tests on both admin pages asserting an unpaid order's fulfillment
renders no Refund form, one asserting the fulfillment page still offers it for
a paid order, and unit tests on `canRefundFulfillment` in `refund.test.ts`.

`make check` green: 1747 tests (from 1741), 99.52% lines / 97.30% branches /
99.53% functions (from 99.52 / 97.29 / 99.53).

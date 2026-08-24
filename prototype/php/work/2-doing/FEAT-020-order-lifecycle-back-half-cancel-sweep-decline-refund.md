---
id: FEAT-020
type: feature
status: open
created: 2026-08-23
---

# FEAT-020: Order lifecycle back half — cancel, stale sweep, seller decline, admin refund

## Problem
`OrderStatus` has `cancelled` and nothing reaches it: there is no cancel route, no stale-order sweep (guest orders hold stock indefinitely), no seller decline, no admin cancel or refund, no reversal in `LedgerEntryType`, and no `refunds` table. `docs/alignment.md` §4 fixes the shared state machines, the `refunds` row, the `refunded` ledger entry and its three timings, the sweep, the sad paths, and the surfaces.

## Goal
The whole money-and-stock lifecycle of an order — happy and sad — is reachable, refused where it must be, and visible on every site.

## Outcome
The order and fulfillment state machines in §4.1 are enforced by the enums and domain rules; the customer can cancel an unpaid order; `make sweep` (an artisan command, also on the scheduler) cancels stale `pending_verification` orders older than `STALE_ORDER_HOURS`; a seller can decline an `awaiting_shipment` fulfillment with a reason and stock returns; an admin can cancel an unpaid order and refund any fulfillment with a reason; every refund writes a `refunds` row and a `refunded` ledger entry so the balance fold matches §4.2 in all three timings; every sad path in §4.3 has a test; the surfaces in §4.4 exist on the storefront, seller portal, and admin site; the counterpart is notified; `docs/orders.md` and `docs/escrow.md` show the new diagrams.

## Why it matters
Refunds and declines decide whether sellers trust the platform and whether the ledger reconciles; the prototypes compete on exactly this.

## Discovery notes
Actions in `app/Actions` (`CancelOrder`, `DeclineFulfillment`, `RefundFulfillment`, `SweepStaleOrders`) inside `DB::transaction` with `lockForUpdate`, refusals as `DomainRuleViolation`, the roll-up of order status from live fulfillments as one pure method. The admin order/fulfillment detail pages come with FEAT-022; sequence the two so the actions have a page to live on.

## Related work
- docs/alignment.md §4
- FEAT-022
- FEAT-003 (commerce core)

## Working

The whole back half of the lifecycle is reachable, refused where §4.1 says it
must be, and visible on all three sites. `make test`: **1514 tests, 4187
assertions**, 100.0 % of lines (from 1349 / 3733 at branch HEAD).

### The two state machines

`App\Domain\Orders\OrderStatus` gained `refunded`:

```
pending_verification ─┬─▶ paid ─┬─▶ partially_shipped ─▶ shipped ─▶ delivered
awaiting_payment ─────┤         │        │                 │           │
payment_failed ───────┘         └────────┴─────────────────┴───────────┴─▶ refunded
       │
       └── (all three) ──▶ cancelled
```

`App\Domain\Orders\FulfillmentStatus` gained `declined` and `refunded`:

```
awaiting_shipment ─ship─▶ shipped ─deliver─▶ delivered
    │  │                    │                   │
 decline refund           refund              refund
    ▼      ▼                ▼                   ▼
 declined  refunded ◀───────┴───────────────────┘
```

`OrderStatus::fromFulfillments()` filters to the **live** fulfillments first
(`FulfillmentStatus::isLive()` — neither declined nor refunded), rolls up over
those, and answers `refunded` when none are left. It is still one pure
function; `RollUpOrderStatus` is unchanged.

Two new predicates carry the rules the actions ask: `releasesStockOnCancel()`
(only `pending_verification` and `awaiting_payment` are holding stock — a
declined card already handed it back) and `hasBeenPaid()` (a decline or refund
needs money to send back; fulfillments exist from placement, so an unpaid
order's fulfillment cannot be refunded).

### The three refund timings

`LedgerBalance::from()` groups movements **by fulfillment** before folding.
That is the one structural change to the ledger: which balance a `refunded`
entry drops cannot be read from a seller's per-type totals, because one sale
refunded after release plus another still held sums to exactly the same three
numbers as the reverse. Per fulfillment, with `escrow = held − released` and
`fromEscrow = max(0, min(escrow, −refunded))`:

| Timing | Entries | Held | Available |
| --- | --- | --- | --- |
| Before release | `held +net`, `refunded −net` | `0` (nothing releases) | `0` |
| After release | `held +net`, `released +net`, `refunded −net` | `0` | drops by `net` |
| After payout | + `paid_out −net` | `0` | `−net`, carried |

`LedgerEntry::totalledByType()` now groups by `(seller_id, fulfillment_id,
type)` to match. Still one query per page — `SellerControllerTest` and
`EarningsControllerTest` still hold the read counts — but the fold sees rows
per fulfillment rather than three per seller, and `docs/escrow.md` and
`docs/admin.md` say so.

`RunWeeklyPayout` needed no fix: `LedgerBalance::isPayable()` is
`available > 0`, so a negative balance writes no `payouts` row and no
`paid_out` entry, and the negative survives into the next period.
`RunWeeklyPayoutTest` now walks both halves — nothing paid while in the red,
and the carried negative netted against the next sale before anything is paid.

### What landed

- `refunds` table (`rfd_`, `unique(fulfillment_id)`), `orders.refunded_cents`,
  `LedgerEntryType::Refunded`, `LedgerMovement::refund()`.
- `CancelOrder`, `DeclineFulfillment`, `RefundFulfillment`, `SweepStaleOrders`,
  and `IssueRefund` (the shared writer, called inside the caller's
  transaction). Every one re-reads the row it writes **inside** that
  transaction, so every §4.3 refusal is judged against the row at write time.
  Stock restoring paths take listing rows through `lockedForPlacement()`.
- `orders:sweep` (`make sweep`, scheduled hourly), `config/orders.php`
  `stale_hours` from `STALE_ORDER_HOURS` (default 24). Idempotent by
  construction: it selects `pending_verification` and leaves `cancelled`.
- `Story::asSystem()` so the sweep's lines carry `actor_type: system`; four
  events added to `StoryEvent` (`order.cancel`, `order.sweep`,
  `fulfillment.decline`, `refund.issue`).
- Surfaces: customer cancel button and declined/refunded fulfillments with
  reason and amount; seller decline form and refund panel, refunded movements
  on earnings; admin cancel on the order, refund per fulfillment on both detail
  pages, refunds table on the order. `refunded` / `declined` / `cancelled`
  filter values fall out of the enums the selects are built from.
- Notifications: `RefundIssued` → `NotifyOfRefund` (customer always;
  seller only when an admin decided, so a seller is not told what they just
  did), `OrderCancelled` → `NotifyOfCancellation` (customer and every seller
  on the order).

### Deliberately left out

- `/admin/accounting`, `/admin/ledger` and its `type=refunded` filter, and the
  `/admin` money tallies — **FEAT-023**.
- Partial refunds. §4.1 fixes the amount at the whole fulfillment subtotal, and
  `refunds.unique(fulfillment_id)` enforces it.
- A `refunds` row in the seeder. Seed data still shows only the happy path.

### What FEAT-023 still has to surface for accounting

The domain side of the fee rule is here as `FulfillmentStatus::isLive()`; no
totals object was added, because nothing on this ticket's surfaces reads one.
FEAT-023 has to compute and render, on `/admin/accounting` and `/admin`:

- `fees_earned_cents` = `sum(fulfillments.fee_cents)` over fulfillments whose
  status `isLive()` — declined and refunded ones are excluded, because the
  platform fee on a refunded fulfillment is forgone (the `refunded` entry runs
  the whole `net_cents` back out and the fee is never collected).
- `fees_refunded_cents` = `sum(fulfillments.fee_cents)` over the fulfillments
  that are declined or refunded.
- Per-seller `refunded` beside held / available / paid out — the
  `LedgerBalance` fold already answers held / available / paid out correctly
  with refunds in the ledger; a `refunded` total is
  `sum(-amount_cents)` over the seller's `refunded` entries.
- `/admin/ledger?type=` — `LedgerEntry::ofType()` is the scope, added here and
  used by the seller earnings page.

### Deviations from §4, and why

- **The ledger fold groups by fulfillment.** §4.2 says "the balance is still a
  fold" and lists the entries for each timing; a flat fold over a seller's four
  type totals cannot produce §4.2's numbers in a mixed portfolio (see above).
  The entries written are exactly the ones §4.2 lists — in particular a refund
  before release writes no `released` row. Node and Rails must group the same
  way or their balances will differ from this one.
- **A cancel notifies every seller on the order**, per §4.4's "seller ← admin
  refund/cancel". For an unpaid order that seller was never told the order
  existed (`ItemSold` fires at payment), so `SaleCancelled` is the first they
  hear of it. Kept for contract symmetry; worth revisiting as a product call.
- **`refunds.payment_id` is nullable.** §4.1 lists the column without saying;
  nullable avoids an unreachable branch (a paid order always has an approved
  payment) that the 100 % line gate would fail on. It is always filled in
  practice.
- **Release still happens on delivery only**, so an admin refund of a
  `shipped` fulfillment is a refund *before* release here, where §4.2 groups
  "shipped/delivered" as after release. The fold reads which case it is from
  the fulfillment's own entries rather than its status, so the arithmetic is
  right either way. Node and Rails will match if they also release on delivery.
- **The sweep runs hourly**, not on a fixed cadence the contract names — §4.1
  only fixes the cutoff, not the schedule.

### For the Node and Rails lanes

- Group the ledger fold by fulfillment (above).
- `refunds` carries `unique(fulfillment_id)`.
- A payout of `≤ 0` writes no row and carries the negative forward.
- Event names and `data` keys: `order.cancel` (`order_id`, `status_from`,
  `status_to`), `order.sweep` (`doing` per order with `order_id`, `did` with
  `cancelled_count`), `fulfillment.decline`, `refund.issue` (`refund_id`,
  `fulfillment_id`, `amount_cents`, `reason`).
- `STALE_ORDER_HOURS` default `24`; the sweep touches only
  `pending_verification`.

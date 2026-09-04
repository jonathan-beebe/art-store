---
id: FEAT-060
type: feature
status: resolved
created: 2026-09-03
---

# FEAT-060: Earnings focuses on this period and the next payout

## Problem
The earnings page (`resources/views/seller/earnings.blade.php`) is four balances, a bar chart of past payouts, and three flat tables of every sale, refund, and payout. It never says when the next payout arrives, how much it will be, or why money is still held.

## Goal
A seller knows what their next payout will be and when, what is still held and why, and how this period compares with the last ones.

## Outcome
- The page names the current payout period (Monday to Sunday) and the day its payout runs.
- Next payout: the amount (released, awaiting payout, plus any carried negative), the date, the count of delivered orders that released it, and one sentence on how money releases. Held in escrow: the amount and the list of the orders holding it, each with its state (not yet shipped, label printed, in transit since) and its net, each opening the order.
- This period: sales, platform fees, refunds, net, with sales change vs the previous period; a bar chart of net per period for the last eight periods with the current one marked; this period's sales as a table (item, buyer, subtotal, fee, net, status).
- Past periods: one row per period (period, orders, sales, fees, refunds, net, payout status and date) and a statement link per period that answers a printable statement page.
- Every number reconciles with the ledger fold (`LedgerBalance`) and the payout rows; a test proves the next-payout amount equals `available`. `make precommit` green; `make check` green before the PR.

## Why it matters
Money is the question a seller asks most and the one the current page answers least directly. Leading with the next payout, showing what holds the rest, and framing the tables by payout period makes the page a report on the business instead of a ledger dump.

## Discovery notes
- `PayoutPeriod` (Monday–Sunday, `endingBefore`) and `LedgerBalance` exist; a pure `PayoutEstimate::from(LedgerBalance, PayoutPeriod, held fulfillments)` under `App\Domain\Seller` can carry amount, date, released count, held total, and carried negative.
- Period aggregates over `fulfillments` by `orders.placed_at` and over `ledger_entries` by `occurred_at`, grouped into periods in PHP.
- The chart is the existing hand-rolled bar idiom on the page; keep it and mark the current period.
- The statement page can be a print-styled Blade view; no PDF library.
- Held rows join FEAT-051's progress for the state line if merged; otherwise `shipped_at` alone.

## Related work
- FEAT-051 (for the held rows' state line)
- docs/escrow.md (hold → release → payout)
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Earnings)

## Working

Pure domain (`App\Domain\Seller`): `PayoutEstimate` (amount straight from
`LedgerBalance::available`, payout date the Monday after the period `$now`
falls in), `HeldState` (`shipped_at` alone, per the discovery note — FEAT-051
not depended on), `HeldOrder`, `SaleFact`/`RefundFact`/`PeriodFigures`
(bucketing sales, live-filtered fees, and refunds into a list of periods),
`PeriodSettlement`/`PeriodPayoutStatus` (a completed period with no `payouts`
row reads as settled at zero, matching `RunWeeklyPayout`'s "no row when not
payable" rule), `PeriodSaleRow`. Added `PayoutPeriod::containing()` and
`::previous()` to the existing escrow domain class rather than duplicating
week arithmetic.

Adapters (`App\Seller`): `NextPayout`, `HeldEscrow` (total from the ledger
fold, not summed from its own rows, so it always reconciles), `EarningsPeriods`
(an eight-period window ending with the period in progress), `PeriodSales`
(every order placed in a period, any status — backs both the current
period's table and the statement).

`EarningsController` rewritten around these; new `StatementController` +
`resources/views/seller/earnings/statement.blade.php`, a standalone
print-friendly page (its own `<html>`, not the seller chrome) with a
`print()` trigger in `public/statement-print.js` rather than an inline
`onclick` — the CSP locks `script-src` to `'self'` outside debug
(`SecurityHeaders`), so an inline handler would silently no-op in
production. Same reasoning replaced an inline-`onclick` row-click table
idiom with a plain link in the cell.

`routes/seller.php`: `earnings` unchanged; added
`earnings/statements/{period}`. First cut constrained `{period}` with a
route-level regex, which broke `GuardedRoutesTest`'s generic sweep (it
substitutes a bare `1` for every route parameter) — dropped the constraint
since the controller already 404s on any string matching no period in the
window, malformed or not.

Two existing tests needed small follow-on fixes, not scope creep: pint's
`strict_comparison` rule turned an intentional `==` on two `DateTimeImmutable`
values into `===`, which would have compared instances rather than values —
resolved by comparing the `PeriodFigures` instances themselves, which are
never rebuilt from scratch, so identity comparison is exactly right. Removing
`tracking-tight` from the earnings page's two hero figures kept them out of
`StatTileTest`'s cross-page scan for the shared stat-tile idiom, since they
are a different, single hero number rather than a member of the four-tile
grid.

Gate: `make precommit` green (composer lint:all + composer test, 4086
passed). Left out: nothing from the ticket's outcome list. Open question for
a later lane: the "This period" bars use the existing hand-rolled percentage
idiom rather than `App\Domain\Analytics\BarStrip`, since that helper assumes
non-negative counts and a period's net can run negative.

### Review pass

The coordinator's review found six money-correctness defects and one
missing acceptance bullet, fixed on the same branch:

1. **Unpaid orders were counted.** A `Fulfillment` row is written at
   `awaiting_shipment` the moment `PlaceOrder` runs, before a card is ever
   charged (`FinalizeOrder` writes the `held` ledger entry, not
   `PlaceOrder`). `EarningsPeriods`, `PeriodSales`, and `HeldEscrow` all read
   fulfillments without checking the order behind them had paid — an order
   stuck at `awaiting_payment` or `payment_failed` showed up as a sale and as
   held escrow. Fixed with a new `Order::hasBeenPaid` scope (and a
   `paidStatuses()` list a `whereHas` closure can reach as a plain
   `whereIn`, since Larastan does not resolve a custom scope called inside
   one), wired into all three. Tests: an unpaid order asserted absent from
   `EarningsPeriodsTest`, `PeriodSalesTest`, and `HeldEscrowTest`.
2. **`PeriodFigures::net()` double-subtracted a same-period refund.** Sales
   were live-status-filtered (a declined sale contributed nothing) and the
   refund was then also subtracted, so a sale refunded in its own period
   read as a net loss the ledger never took. Sales and fees are now gross —
   every paid order placed that period, whatever its current status — and a
   refund nets itself back out through `refunds`, dated by when it
   happened. A same-period refund now reads `net() === 0`, matching the
   ledger; a later-period refund leaves the sale's own period's sales and
   fees untouched and lands the refund in the period it happened in
   instead. This also made `orderCount` and `sales`/`fees` describe the
   same population, so `SaleFact.isLive` is gone.
3. **`HeldEscrow`'s list and total could diverge.** The list read every
   `awaiting_shipment`/`shipped` fulfillment; the total read the ledger
   fold. An unpaid order's fulfillment appeared in the list (net > 0) but
   not in the fold's `held` (no `held` entry was ever written), so the two
   would not reconcile. Fixed by the same paid-order constraint; a new test
   sums the rows and asserts the sum equals `$total`.
4. **The acceptance bullet for a sales-change comparison was missing.**
   `PeriodFigures::salesChange()` reuses `App\Domain\Analytics\RangeChange`
   against a given previous period; `EarningsPeriods::currentSalesChange()`
   composes it against the period right before the current one. The
   earnings page's Sales tile shows it, colored by direction.
5. **A negative period's bar looked identical to a positive one.** Bar
   height was `abs(net)`, so a loss period read as tall as a gain. Bars for
   a negative net now tint red and carry an `sr-only` label naming the
   period, its net, and "a net loss".
6. **A Sunday delivery could count toward two payouts.** `NextPayout`
   compared `delivered_at > $payout->period_end`, and `period_end` is a
   `date` cast — midnight of the settled period's last day — so a delivery
   later that same Sunday (still inside the settled period) read as
   "since the last payout" a second time. Fixed with
   `$payout->period_end->endOfDay()`; `NextPayoutTest` covers a Sunday
   delivery through a payout run.

Also fixed, not money-correctness but flagged in the same pass:

- `FulfillmentStatus::sellerBadgeTint()` replaces the same
  status-to-tint `match` block duplicated in `orders/show.blade.php`,
  `earnings.blade.php`, and `earnings/statement.blade.php`.
- `EarningsControllerTest`'s "shows this period's sale" test passed even
  with the sales table deleted, since the same dollar amounts print
  elsewhere on the page — rewritten to assert the row's item title and its
  order link, using a delivered fulfillment so it cannot also be read from
  the held-in-escrow list. Added a page test for the "Carried balance"
  badge (a refund after payout, driving `available` negative) and a
  statement test that reconciles its displayed figures with the seller's
  own ledger fold.
- `PeriodPayoutStatus::Pending` was unreachable (`payouts.paid_at` is
  `NOT NULL`; `RunWeeklyPayout` always sets it at creation) — removed, with
  its branch in both blade files and its test.
- `EarningsController`/`StatementController` read
  `Illuminate\Support\Facades\Date::now()` directly; every other controller
  reads the shared `Controller::now()` — caught while fixing FEAT-061's
  `SupportController` and fixed here too.

Gate after the review pass: `make check` green (lint → assets → the
coverage-gated suite, 4144 tests passed).

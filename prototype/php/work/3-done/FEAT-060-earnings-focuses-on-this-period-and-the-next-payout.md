---
id: FEAT-060
type: feature
status: open
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

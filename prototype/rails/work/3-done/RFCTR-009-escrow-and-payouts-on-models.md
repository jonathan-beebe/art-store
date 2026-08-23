---
id: RFCTR-009
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-009: Escrow balance and weekly payout on LedgerEntry, Seller and Payout

## Problem
`Escrow::RunWeeklyPayout` under `src/app/actions/escrow` and `Domain::Escrow::{Fee,LedgerBalance,LedgerEntryType,LedgerMovement,PayoutPeriod}`, `Domain::Reports::PayoutSummary` carry the escrow rules; `Seller#escrow_balance` and `CommerceTestCase#balance_of` fold ledger rows through `to_movement`.

## Goal
Escrow reads as ledger rows and a payout run on the models that store them.

## Outcome
`Payout.run_weekly(as_of:)` (or equivalent) performs the run the rake task and the debug button call; a seller's held/available/paid-out balance and the 10% fee are methods on the models; `app/actions/escrow` and the listed domain files are gone; the payout, ledger and rake-task tests pass unchanged.

## Why it matters
The fee, the period math and the balance fold are the core of earnings; a reader of `ledger_entry.rb` sees only an enum and a scope.

## Discovery notes
`Money` (integer cents) stays a plain value object. `PayoutPeriod` is a candidate for a small plain model in `app/models`. The `payouts:run` task output lines are asserted by its test.

## Related work
- RFCTR-008
- RFCTR-011

## Working

Verified first that the escrow rules had one entry point each: `Order#pay!`
held, `Fulfillment#deliver!` released, `Escrow::RunWeeklyPayout` paid out, and
every one of them built a `Domain::Escrow::LedgerMovement` before writing the
row. The sign convention lived in that value object, so collapsing it meant
moving the three writers onto `LedgerEntry` — `hold`, `release` and `pay_out` —
and letting the comment above them carry the reason the signs differ.

`LedgerEntry.balance` folds a relation into `LedgerEntry::Balance` (held,
available, paid_out, `payable?`), which is a `Data` value nested in the model
so the fold and the enum it reads sit in one file. The fold is now a grouped
SQL sum rather than a map over loaded rows. `Seller#escrow_balance` is
`ledger_entries.balance`, and `LedgerEntry.balances_by_seller` gives the payout
run every seller's balance from one query.

`Payout.run_weekly(as_of: Time.current)` replaces the action: it takes the
period, folds the ledger as of its close, and writes a payout plus a `paid_out`
entry dated at the close for every payable seller. `PayoutPeriod` moved to
`app/models/payout_period.rb` unchanged. The 10% fee is
`Fulfillment::PLATFORM_FEE_PERCENT` with `Fulfillment.fee_for` /
`Fulfillment.net_for`, called from `Order.split_by_seller` — the fee belongs to
the row that stores it. `Domain::Reports::PayoutSummary` folded into two lines
in `Seller::PayoutsController#create`, which needed `Domain::Money.zero`.

Deleted `app/actions/escrow/`, `app/domain/escrow/` and
`app/domain/reports/payout_summary.rb`. Tests moved to
`test/models/ledger_entry_test.rb` (writers, fold, `occurred_by`,
`balances_by_seller`), `test/models/payout_test.rb` (the weekly run),
`test/models/payout_period_test.rb`, `test/models/fulfillment_test.rb` (fee
rounding) and `test/models/seller_test.rb`. `balance_of` is gone from
`test/support/test_records.rb`; callers read `seller.escrow_balance`, and the
tests that matched on an entry type now use the enum scopes.

Left alone: `Domain::Money` stays in `app/domain/` for RFCTR-011, the rake
task's output lines, the payout flash text, and `PayoutPeriod#covers?`, which
has no caller in the app but is part of the period contract the tests hold.

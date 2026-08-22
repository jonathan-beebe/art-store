---
id: RFCTR-009
type: refactor
status: open
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

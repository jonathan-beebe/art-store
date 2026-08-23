---
id: RFCTR-001
type: refactor
status: open
created: 2026-08-23
---

# RFCTR-001: Escrow and payout decisions move into core

## Problem
Escrow folding, the one-payout-per-period rule, and payout date/arithmetic are
implemented in the shell, in several places each, instead of once in core.

**Per-seller ledger folding is written three times.**
`app/actions/escrow/run-weekly-payout.ts:29-32,45-71` groups ledger movements
into a `Map<sellerId, movements[]>` and folds each with `ledgerBalance`.
`app/sites/admin/queries/seller-accounts.ts:73-80` (`balancesBySeller`) does
the identical grouping with slightly different code.
`app/sites/admin/queries/seller-rows.ts:69-80` does the same grouping a third
time. Separately, `if (settled.has(sellerId)) continue` inside the payout loop
in `run-weekly-payout.ts` is the business rule "a seller has at most one
payout per period," stated only in the action — no core function owns it.

**Payout date parsing and payout arithmetic live in an admin route.**
`app/sites/admin/routes/payouts.ts:15,54-69` — `parseAsOf(value, fallback)`
reimplements "which day is this payout run for" with its own `DAY_PATTERN`
and `new Date(\`${value}T00:00:00.000Z\`)`, carrying a comment that the CLI's
version (`app/cli/parse-as-of.ts`) can't be reused because it reads `argv`.
The same route's `payoutFlashMessage` does
`payouts.reduce((sum, payout) => addCents(sum, payout.amountCents), 0)` and
composes the summary sentence — money folding and copy inside a handler.

**`sellerBalance` reads the entire ledger to answer for one seller.**
`app/actions/escrow/seller-balance.ts:15-17` and
`app/actions/escrow/ledger-movements.ts:15-23` — `ledgerMovements` selects
every row in `ledger_entries` for every seller with no predicate, and
`sellerBalance` filters in JS with
`.filter((movement) => movement.sellerId === sellerId)`. The migration
creates `ledger_entries_seller_id_occurred_at_index`
(`20260823000004-create-escrow.ts:38-42`), which this query can never use.
Called from `app/sites/admin/queries/seller-detail.ts:34`,
`app/sites/seller/routes/home.ts`, and `app/sites/seller/routes/earnings.ts`
— a per-seller page scanning the whole platform ledger on every load.

**`currentCustomerStanding` over-reads and does not use its index.**
`app/actions/moderation/current-customer-standing.ts:14-19` and
`app/actions/moderation/active-customer-block.ts:22-27` both read every
block row a customer has ever had (`where customerId = ?` only) and let core
pick the unlifted one. The index at
`20260823000006-create-customer-blocks.ts:18-22` is on
`(customer_id, lifted_at)`; neither query supplies the second column. This
runs on every write-path request through `refuseBlockedCustomer` and on
every message post via `conversationActor`.

**Argument parsing is hand-rolled where `node:util.parseArgs` exists.**
`app/cli/parse-as-of.ts:1-18` scans argv itself:
`argv.find((argument) => argument.startsWith('--as-of='))`, which accepts
only the `=` form — `--as-of 2026-08-24` (space form) is silently ignored
and the fallback wins instead of erroring. `app/db/migrate.ts:7` does
`process.argv.includes('--fresh')`, which also matches `--fresh` appearing
as the *value* of another flag. `app/sites/admin/routes/payouts.ts:54-61`
carries a third, independent date parser with a comment acknowledging it
duplicates `parseAsOf`.

## Goal
Escrow balance folding, the one-payout-per-period rule, and the "as of which
day" parse each have exactly one implementation, in core, with the CLIs
parsing argv through the platform's own parser.

## Outcome
- `ledgerBalancesBySeller` and `planWeeklyPayout` are pure core functions
  with literal-input tests, and the payout action only reads and applies
  their result.
- One `parseAsOfDay` function is shared by the CLI and the admin route.
- Both CLIs parse argv with `node:util.parseArgs`, accepting both the `=`
  and space-separated forms and rejecting unknown flags.
- `sellerBalance` queries one seller instead of scanning the whole ledger.

## Why it matters
Business rules and arithmetic belong in core; the shell reads, applies, and
writes. The per-seller folding is the same rule implemented three times —
the duplication-felt-three-times threshold for abstraction is met, and two
of the three implementations can silently drift from each other. The
one-payout-per-period rule currently exists only as an `if` inside an
action, invisible to anything that isn't that action. Argument parsing is a
named platform-wins item: `node:util.parseArgs` ships in Node 24 and
already handles both flag forms and rejects unknown flags, which the
hand-rolled scanners do not.

## Discovery notes
Add `ledgerBalancesBySeller(movements: readonly (LedgerMovement & { sellerId: number })[]): ReadonlyMap<number, LedgerBalance>`
to `app/core/escrow/ledger-balance.ts` and have `run-weekly-payout.ts`,
`seller-accounts.ts`, and `seller-rows.ts` all call it instead of folding
independently. Make the payout decision itself a core function —
`planWeeklyPayout({ balances, alreadySettledSellerIds, period })` returning
the payout intents — so the action only reads, applies, and writes; the
"already settled" check becomes a core parameter instead of an inline `if`.

Split `app/cli/parse-as-of.ts` into a pure `parseAsOfDay(value: string | undefined, fallback: Date): Date`
in core plus a thin argv reader that calls it; the admin route and the CLI
both call the pure function. Give `ledgerMovements` an optional `sellerId`
predicate so `sellerBalance` can pass it through rather than filtering in
JS; the folding itself stays in core. Add `.where('liftedAt', 'is', null)`
to the two customer-standing queries and keep the ordering fold in core.

Replace the argv scan in `parse-as-of.ts` and the `--fresh` check in
`migrate.ts` with `node:util.parseArgs`, keeping `parseAsOf`'s existing
`(argv, fallback) => Date` signature so its sidecar test still applies; add
cases for the space-separated form and an unknown flag. The Makefile's
`payouts` target passes `--as-of=$(AS_OF)` — keep that form working.

Files this ticket is expected to touch: `app/core/escrow/ledger-balance.ts`
(new function), `app/actions/escrow/run-weekly-payout.ts`,
`app/actions/escrow/seller-balance.ts`, `app/actions/escrow/ledger-movements.ts`,
`app/sites/admin/queries/seller-accounts.ts`,
`app/sites/admin/queries/seller-rows.ts`,
`app/sites/admin/routes/payouts.ts`, `app/cli/parse-as-of.ts` and its test,
`app/db/migrate.ts`, `app/actions/moderation/current-customer-standing.ts`,
`app/actions/moderation/active-customer-block.ts`.

This ticket, along with RFCTR-002 and RFCTR-003, must land before IMPRV-002
(validation declared on routes) — IMPRV-002 touches the same route handlers
this ticket is pulling logic out of, and doing that pull first keeps
IMPRV-002's route-body changes small and stable.

## Related work
- 03-core-shell.md — "Per-seller ledger folding and the one-payout-per-period rule live in the shell, three times"
- 03-core-shell.md — "Payout date parsing and payout arithmetic in an admin route"
- 04-data-layer.md — "`sellerBalance` reads the entire ledger to answer for one seller"
- 04-data-layer.md — "`currentCustomerStanding` over-reads and does not use its index"
- 01-deps-platform.md — "Argument parsing hand-rolled where `node:util.parseArgs` exists"
- 02-types-boundaries.md — "CLI arguments are read ad hoc rather than parsed once"
- IMPRV-002 (validation on routes) depends on this ticket landing first

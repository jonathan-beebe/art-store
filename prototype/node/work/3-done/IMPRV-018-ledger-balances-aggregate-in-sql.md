---
id: IMPRV-018
type: improvement
status: resolved
created: 2026-08-25
resolved: 2026-08-25
---

# IMPRV-018: Ledger balances aggregate in SQL

## Problem
Balances are recomputed from full history in JS on every money-page render:

- `ledgerMovements` (`app/actions/escrow/ledger-movements.ts:13-28`) with no filters is a full `ledger_entries` scan ordered `sellerId, occurredAt, id`.
- `platformMoney` (`sites/admin/queries/platform-money.ts:22`) folds that whole scan, plus all refunds, plus a fulfillments×ledger join.
- `sellerAccounts` (`sites/admin/queries/seller-accounts.ts:42-52`) scans the ledger again with four further whole-table aggregations.
- `sellerBalance` (`app/actions/escrow/seller-balance.ts`) folds all of one seller's rows per seller-dashboard view.
- The fold itself is superlinear: `core/escrow/ledger-balance.ts:82` appends to groups with `bySeller.set(id, [...(bySeller.get(id) ?? []), movement])` — a rebuilt array per row, O(n²) per group over the whole ledger.
- `platformTallies` (`sites/admin/queries/platform-tallies.ts:32-33`) loads every customer row to count verified ones, where the sibling tallies on the same page already GROUP BY in SQL.
- Minor, same neighborhood: `ledger-rows.ts:48-49` orders by `occurredAt DESC` twice.

## Goal
Money pages read balances as aggregates rather than folding history.

## Outcome
Admin money pages and the seller dashboard cost O(sellers) / O(page) rather than O(ledger); any JS folds that remain build their groups linearly; the pure balance rules stay the executable spec, with tests pinning the SQL aggregates to them.

## Why it matters
These pages get slower with every sale forever — the render cost is proportional to all money movement in the system's history, in JS, on the event loop, twice on some pages. The doctrine's answer is not a materialized balance at this scale; it is letting SQL do the arithmetic the `(seller_id, occurred_at)` index can feed.

## Discovery notes
- `SUM(CASE entry_type ...) GROUP BY seller_id` shapes cover the balance and the per-seller account rows; keep `ledgerBalance` as the pure spec and characterization-test the SQL against it.
- For folds that remain, the get-or-create-then-push idiom already used at `core/cart/cart-totals.ts:24-27` is the repo's own shape.
- `platformTallies`' verified count is a `count(*)` with a `CASE`/`FILTER` on `email is null`, pinned to `isVerifiedCustomer` by a test.

## Related work
- FEAT-019 (refund lifecycle added the ledger entry types these folds walk)
- IMPRV-019 (pagination and SQL-side filters for the list pages proper)

## Working

2026-08-25 — re-validated: every cited fold is still in place on `node/performance`.
Two further callers of the same whole-ledger fold found: `sites/admin/queries/seller-rows.ts:40-41`
and `actions/escrow/run-weekly-payout.ts:52-54` — both switch to the same grouped aggregate.

### Design

New action `app/actions/escrow/ledger-balances.ts` owns the SQL aggregate
(`sql` tagged-template, snake_case in raw fragments — `CamelCasePlugin` leaves
raw text alone):

- `sellerBalances(context, occurredBy?)` → `ReadonlyMap<SellerId, LedgerBalance>` (`GROUP BY seller_id`)
- `sellerBalance(context, sellerId, occurredBy?)` → `LedgerBalance` (zero balance on no rows)
- `platformBalance(context)` → `LedgerBalance` (no grouping)

Bucket arithmetic, matching `ledgerBalance` exactly:

```
held      = SUM(CASE entry_type WHEN 'held' THEN amount_cents
                                WHEN 'released' THEN -amount_cents
                                WHEN 'refunded' THEN CASE WHEN <released> THEN 0 ELSE amount_cents END
                                ELSE 0 END)
available = SUM(CASE entry_type WHEN 'released' THEN amount_cents
                                WHEN 'paid_out' THEN amount_cents
                                WHEN 'refunded' THEN CASE WHEN <released> THEN amount_cents ELSE 0 END
                                ELSE 0 END)
paid_out  = SUM(CASE WHEN entry_type = 'paid_out' THEN -amount_cents ELSE 0 END)

<released> = EXISTS (SELECT 1 FROM ledger_entries released
                     WHERE released.entry_type = 'released'
                       AND released.fulfillment_id = ledger_entries.fulfillment_id
                       [AND released.occurred_at <= :occurredBy])
```

The `occurredBy` bound applies to the outer scan and the `<released>` EXISTS
both — the JS fold computes its released set from the same bounded read, so a
release after the cutoff leaves the refund in `held` as of the cutoff. A
refund with a null `fulfillment_id` lands in `held` (NULL never matches the
EXISTS equality, same as the fold's null guard). A partial index
`ledger_entries(fulfillment_id) WHERE entry_type = 'released'` feeds the
EXISTS; added to the create-escrow migration in place (`make fresh`).

Callers:
- `seller-balance.ts` deleted; `earnings.ts`, `home.ts`, `seller-detail.ts` import `sellerBalance` from `ledger-balances.ts`.
- `seller-accounts.ts` — balances via `sellerBalances`; `lifetimeSalesBySeller` and `refundTotalsBySeller` become `SUM ... GROUP BY seller_id` in SQL.
- `seller-rows.ts` — balances via `sellerBalances`.
- `platform-money.ts` — `platformBalance`; `refundedTotal` becomes `SUM(amount_cents)`; fee totals become `SUM(CASE WHEN status IN (reversed) ...)` over the fulfillments×held join, with the reversed list exported from `fulfillment-status.ts` so SQL and `isReversed` share one source.
- `run-weekly-payout.ts` — `sellerBalances(transacted, endsAt)`.
- `platform-tallies.ts` — `count(*)` + `count(email)` in one query (`count(column)` counts non-null, which is `isVerifiedCustomer`), pinned by test.
- `ledger-balance.ts:80-83` — get-or-create-then-push accumulator (cart-totals idiom); `ledgerBalance`/`ledgerBalancesBySeller` stay as the pure executable spec, characterization tests pin the SQL to them via `ledgerMovements`.
- `ledger-rows.ts` — drop the duplicated `occurredAt DESC` order key.

Safety net: `issue-refund.test.ts` already asserts `sellerBalance` across
refund-before-release and refund-after-release through the real actions;
route tests pin the page numbers.

### Result

Landed as designed; the escape hatch (a remaining JS fold) was not needed —
every listed fold converted to SQL. `sellerBalance` also groups by `seller_id`
filtered to one seller, so the zero-row case is a real branch the empty-ledger
test exercises. 14 new tests in `ledger-balances.test.ts` characterize the SQL
against `ledgerBalance`/`ledgerBalancesBySeller` over `ledgerMovements`,
including refund-before-release, refund-after-release, null-fulfillment
refunds, and an `occurredBy` cutoff between a refund and its later release.
`platform-tallies.test.ts` pins the `count(email)` verified count to
`isVerifiedCustomer`; `platform-money.test.ts` pins the fee SQL to `feeTotals`
across all five fulfillment statuses. Suite: 1942 → 1952 tests, coverage
99.45/95.86/99.43 (gate 95/90). Validation review: accept, no defects.

Left in place, out of scope: the same array-rebuild grouping idiom at
`sites/seller/queries/fulfillments.ts:102` and
`sites/admin/queries/order-rows.ts:90` — per-order/per-fulfillment groups,
IMPRV-019 neighborhood.

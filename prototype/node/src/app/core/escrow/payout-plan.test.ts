import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { planWeeklyPayout, payoutTotal } from './payout-plan.ts'
import type { LedgerBalance } from './ledger-balance.ts'
import type { PayoutPeriod } from './payout-period.ts'
import { cents } from '../money.ts'

const PERIOD: PayoutPeriod = { firstDay: '2026-08-17', lastDay: '2026-08-23' }
const ZERO_BALANCE: LedgerBalance = { heldCents: cents(0), availableCents: cents(0), paidOutCents: cents(0) }

function balance(overrides: Partial<LedgerBalance> = {}): LedgerBalance {
  return { ...ZERO_BALANCE, ...overrides }
}

test('an empty ledger pays nobody', () => {
  const intents = planWeeklyPayout({ balances: new Map(), settledSellerIds: new Set(), period: PERIOD })

  assert.deepEqual(intents, [])
})

test('a seller with only held money is not payable', () => {
  const balances = new Map([[fixtureId('sel', 1), balance({ heldCents: cents(40_500) })]])

  const intents = planWeeklyPayout({ balances, settledSellerIds: new Set(), period: PERIOD })

  assert.deepEqual(intents, [])
})

test('a seller with available money gets an intent for the period', () => {
  const balances = new Map([[fixtureId('sel', 1), balance({ availableCents: cents(40_500) })]])

  const intents = planWeeklyPayout({ balances, settledSellerIds: new Set(), period: PERIOD })

  assert.deepEqual(intents, [
    { sellerId: fixtureId('sel', 1), amountCents: 40_500, periodStart: '2026-08-17', periodEnd: '2026-08-23' },
  ])
})

test('an already-settled seller is skipped even though their balance is payable', () => {
  const balances = new Map([[fixtureId('sel', 1), balance({ availableCents: cents(40_500) })]])

  const intents = planWeeklyPayout({ balances, settledSellerIds: new Set([fixtureId('sel', 1)]), period: PERIOD })

  assert.deepEqual(intents, [])
})

test('each payable, unsettled seller gets their own intent', () => {
  const balances = new Map([
    [fixtureId('sel', 1), balance({ availableCents: cents(40_500) })],
    [fixtureId('sel', 2), balance({ availableCents: cents(9_000) })],
  ])

  const intents = planWeeklyPayout({ balances, settledSellerIds: new Set([fixtureId('sel', 2)]), period: PERIOD })

  assert.deepEqual(intents, [{ sellerId: fixtureId('sel', 1), amountCents: 40_500, periodStart: '2026-08-17', periodEnd: '2026-08-23' }])
})

test('a period that crosses a year boundary is carried through to the intent unchanged', () => {
  const yearBoundary: PayoutPeriod = { firstDay: '2025-12-29', lastDay: '2026-01-04' }
  const balances = new Map([[fixtureId('sel', 1), balance({ availableCents: cents(5_000) })]])

  const intents = planWeeklyPayout({ balances, settledSellerIds: new Set(), period: yearBoundary })

  assert.deepEqual(intents, [
    { sellerId: fixtureId('sel', 1), amountCents: 5_000, periodStart: '2025-12-29', periodEnd: '2026-01-04' },
  ])
})

test('payoutTotal sums nothing for no payouts', () => {
  assert.equal(payoutTotal([]), 0)
})

test('payoutTotal adds every payout amount', () => {
  assert.equal(payoutTotal([{ amountCents: cents(40_500) }, { amountCents: cents(9_000) }]), 49_500)
})

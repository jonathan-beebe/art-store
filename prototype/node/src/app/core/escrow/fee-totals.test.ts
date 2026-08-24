import { test } from 'node:test'
import assert from 'node:assert/strict'
import { ZERO_FEE_TOTALS, feeTotals } from './fee-totals.ts'
import { cents } from '../money.ts'

test('no sales earn nothing', () => {
  assert.deepEqual(feeTotals([]), ZERO_FEE_TOTALS)
})

test('a live fulfillment earns its fee whatever stage it is at', () => {
  const totals = feeTotals([
    { feeCents: cents(4_500), status: 'awaiting_shipment' },
    { feeCents: cents(900), status: 'shipped' },
    { feeCents: cents(100), status: 'delivered' },
  ])

  assert.deepEqual(totals, { earnedCents: 5_500, refundedCents: 0 })
})

test('a declined or refunded fulfillment forgoes its fee', () => {
  const totals = feeTotals([
    { feeCents: cents(4_500), status: 'declined' },
    { feeCents: cents(900), status: 'refunded' },
  ])

  assert.deepEqual(totals, { earnedCents: 0, refundedCents: 5_400 })
})

test('a mixed order splits the fees across both totals', () => {
  const totals = feeTotals([
    { feeCents: cents(4_500), status: 'shipped' },
    { feeCents: cents(900), status: 'declined' },
  ])

  assert.deepEqual(totals, { earnedCents: 4_500, refundedCents: 900 })
})

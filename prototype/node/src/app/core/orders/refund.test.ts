import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  REFUND_ISSUER_TYPES,
  REFUND_REASON_MAX_LENGTH,
  canRefundFulfillment,
  parseRefundReason,
  planRefund,
  type RefundSubject,
} from './refund.ts'
import { cents } from '../money.ts'
import { fixtureId } from '../../test/fixture-ids.ts'

const CHARGE = fixtureId('pay', 1)

function paidSale(overrides: Partial<RefundSubject> = {}): RefundSubject {
  return {
    issuedByType: 'seller',
    status: 'awaiting_shipment',
    subtotalCents: cents(45_000),
    netCents: cents(40_500),
    paymentId: CHARGE,
    ...overrides,
  }
}

test('REFUND_ISSUER_TYPES names both issuers', () => {
  assert.deepEqual(REFUND_ISSUER_TYPES, ['seller', 'admin'])
})

test('a seller decline lands the fulfillment in declined', () => {
  const plan = planRefund(paidSale())

  assert.equal(plan.ok, true)
  assert.equal(plan.ok && plan.intent.status, 'declined')
})

test('a seller decline refunds the whole subtotal and reverses the whole net', () => {
  const plan = planRefund(paidSale())

  assert.equal(plan.ok && plan.intent.amountCents, 45_000)
  assert.deepEqual(plan.ok && plan.intent.movement, { entryType: 'refunded', amountCents: -40_500 })
})

test('a seller decline hands the stock back', () => {
  const plan = planRefund(paidSale())

  assert.equal(plan.ok && plan.intent.stockChange, 'restore')
})

test('an admin refund lands the fulfillment in refunded and restores nothing', () => {
  const plan = planRefund(paidSale({ issuedByType: 'admin' }))

  assert.equal(plan.ok && plan.intent.status, 'refunded')
  assert.equal(plan.ok && plan.intent.stockChange, 'keep')
})

test('an admin refunds a shipped or delivered fulfillment', () => {
  for (const status of ['shipped', 'delivered'] as const) {
    const plan = planRefund(paidSale({ issuedByType: 'admin', status }))

    assert.equal(plan.ok, true)
  }
})

test('an admin refunds for a silent seller before the piece ships', () => {
  assert.equal(planRefund(paidSale({ issuedByType: 'admin', status: 'awaiting_shipment' })).ok, true)
})

test('a seller cannot decline after shipping', () => {
  const plan = planRefund(paidSale({ status: 'shipped' }))

  assert.deepEqual(plan, { ok: false, refusal: 'A fulfillment cannot move from shipped to declined.' })
})

test('a seller cannot decline after delivery', () => {
  assert.equal(planRefund(paidSale({ status: 'delivered' })).ok, false)
})

test('a fulfillment already declined is refused a second reversal', () => {
  assert.equal(planRefund(paidSale({ status: 'declined' })).ok, false)
  assert.equal(planRefund(paidSale({ issuedByType: 'admin', status: 'declined' })).ok, false)
})

test('a fulfillment already refunded is refused a second refund', () => {
  const plan = planRefund(paidSale({ issuedByType: 'admin', status: 'refunded' }))

  assert.deepEqual(plan, { ok: false, refusal: 'A fulfillment cannot move from refunded to refunded.' })
})

test('the intent carries the charge the refund goes back against', () => {
  const plan = planRefund(paidSale())

  assert.equal(plan.ok && plan.intent.paymentId, CHARGE)
})

test('an unpaid order has nothing to refund', () => {
  const plan = planRefund(paidSale({ paymentId: null }))

  assert.deepEqual(plan, { ok: false, refusal: 'An order that has not been paid cannot be refunded.' })
})

test('an unpaid order is refused before the transition is even considered', () => {
  const plan = planRefund(paidSale({ issuedByType: 'admin', status: 'refunded', paymentId: null }))

  assert.equal(plan.ok === false && plan.refusal.includes('has not been paid'), true)
})

test('a reason is trimmed', () => {
  assert.deepEqual(parseRefundReason('  Damaged in the kiln  '), { ok: true, value: 'Damaged in the kiln' })
})

test('a blank reason is refused', () => {
  assert.deepEqual(parseRefundReason('   '), { ok: false, error: 'Enter a reason.' })
  assert.equal(parseRefundReason(null).ok, false)
  assert.equal(parseRefundReason(undefined).ok, false)
})

test('a reason of exactly the maximum length is accepted', () => {
  assert.equal(parseRefundReason('x'.repeat(REFUND_REASON_MAX_LENGTH)).ok, true)
})

test('an unpaid fulfillment cannot be refunded, whatever its status', () => {
  assert.equal(canRefundFulfillment({ status: 'awaiting_shipment', paymentId: null }), false)
  assert.equal(canRefundFulfillment({ status: 'shipped', paymentId: null }), false)
})

test('a paid fulfillment can be refunded up through delivered', () => {
  for (const status of ['awaiting_shipment', 'shipped', 'delivered'] as const) {
    assert.equal(canRefundFulfillment({ status, paymentId: CHARGE }), true)
  }
})

test('a paid fulfillment already declined or refunded cannot be refunded again', () => {
  assert.equal(canRefundFulfillment({ status: 'declined', paymentId: CHARGE }), false)
  assert.equal(canRefundFulfillment({ status: 'refunded', paymentId: CHARGE }), false)
})

test('a reason past the maximum length is refused', () => {
  const parsed = parseRefundReason('x'.repeat(REFUND_REASON_MAX_LENGTH + 1))

  assert.deepEqual(parsed, { ok: false, error: 'Keep the reason under 500 characters.' })
})

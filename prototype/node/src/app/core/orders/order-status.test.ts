import { test } from 'node:test'
import assert from 'node:assert/strict'
import { TransitionError } from '../transition-error.ts'
import {
  ORDER_STATUSES,
  ORDER_STATUS_TRANSITIONS,
  canTransitionOrder,
  transitionOrder,
  orderStatusForPlacement,
  orderStatusAfterVerification,
  orderStatusFromCardDecision,
  orderStatusFromFulfillments,
  isCancellable,
} from './order-status.ts'
import { approvedCard, declinedCard } from '../payments/card-decision.ts'

test('every status has a transition list', () => {
  assert.deepEqual(Object.keys(ORDER_STATUS_TRANSITIONS).sort(), [...ORDER_STATUSES].sort())
})

test('verifying an email opens payment', () => {
  assert.equal(canTransitionOrder('pending_verification', 'awaiting_payment'), true)
})

test('an order awaiting payment is paid or fails', () => {
  assert.equal(canTransitionOrder('awaiting_payment', 'paid'), true)
  assert.equal(canTransitionOrder('awaiting_payment', 'payment_failed'), true)
})

test('a guest cannot pay before verifying', () => {
  assert.equal(canTransitionOrder('pending_verification', 'paid'), false)
})

test('a failed payment retries', () => {
  assert.equal(canTransitionOrder('payment_failed', 'paid'), true)
})

test('a retry that is declined again stays where it was', () => {
  assert.equal(canTransitionOrder('payment_failed', 'payment_failed'), true)
})

test('a paid order ships whole or in part', () => {
  assert.equal(canTransitionOrder('paid', 'shipped'), true)
  assert.equal(canTransitionOrder('paid', 'partially_shipped'), true)
})

test('a delivered order goes nowhere', () => {
  assert.deepEqual(ORDER_STATUS_TRANSITIONS.delivered, [])
})

test('a cancelled order goes nowhere', () => {
  assert.deepEqual(ORDER_STATUS_TRANSITIONS.cancelled, [])
})

test('a paid order cannot be paid twice', () => {
  assert.throws(
    () => transitionOrder('paid', 'paid'),
    (error: unknown) => error instanceof TransitionError && error.message === 'An order cannot move from paid to paid.',
  )
})

test('a verified purchaser places an order that awaits payment', () => {
  assert.equal(orderStatusForPlacement(true), 'awaiting_payment')
})

test('a guest places an order that awaits verification', () => {
  assert.equal(orderStatusForPlacement(false), 'pending_verification')
})

test('verification moves an order that was waiting on it', () => {
  assert.equal(orderStatusAfterVerification('pending_verification'), 'awaiting_payment')
})

test('verification leaves every other status alone', () => {
  assert.equal(orderStatusAfterVerification('paid'), 'paid')
  assert.equal(orderStatusAfterVerification('awaiting_payment'), 'awaiting_payment')
})

test('an approved card pays the order', () => {
  assert.equal(orderStatusFromCardDecision(approvedCard('4242')), 'paid')
})

test('a declined card fails the payment', () => {
  assert.equal(orderStatusFromCardDecision(declinedCard('0002', 'generic_decline')), 'payment_failed')
})

test('an order whose fulfillments all delivered is delivered', () => {
  assert.equal(orderStatusFromFulfillments(['delivered', 'delivered']), 'delivered')
})

test('an order whose fulfillments all departed is shipped', () => {
  assert.equal(orderStatusFromFulfillments(['shipped', 'delivered']), 'shipped')
})

test('an order with one fulfillment still in the studio is partially shipped', () => {
  assert.equal(orderStatusFromFulfillments(['delivered', 'awaiting_shipment']), 'partially_shipped')
})

test('an order whose fulfillments all await shipment is paid', () => {
  assert.equal(orderStatusFromFulfillments(['awaiting_shipment', 'awaiting_shipment']), 'paid')
})

test('an order rolls up from at least one fulfillment', () => {
  assert.throws(() => orderStatusFromFulfillments([]), RangeError)
})

test('isCancellable names the three statuses a customer can cancel from', () => {
  assert.equal(isCancellable('pending_verification'), true)
  assert.equal(isCancellable('awaiting_payment'), true)
  assert.equal(isCancellable('payment_failed'), true)
})

test('isCancellable refuses a paid order', () => {
  assert.equal(isCancellable('paid'), false)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { BrokenContractError } from '../defect.ts'
import { refused } from '../refusal.ts'
import {
  ORDER_STATUSES,
  ORDER_STATUS_TRANSITIONS,
  canTransitionOrder,
  transitionOrder,
  orderMovedTo,
  orderTransitionRefusalCopy,
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

test('a delivered order goes nowhere but a refund', () => {
  assert.deepEqual(ORDER_STATUS_TRANSITIONS.delivered, ['refunded'])
})

test('a refunded order goes nowhere', () => {
  assert.deepEqual(ORDER_STATUS_TRANSITIONS.refunded, [])
})

test('every paid stage reaches refunded', () => {
  assert.equal(canTransitionOrder('paid', 'refunded'), true)
  assert.equal(canTransitionOrder('partially_shipped', 'refunded'), true)
  assert.equal(canTransitionOrder('shipped', 'refunded'), true)
  assert.equal(canTransitionOrder('delivered', 'refunded'), true)
})

test('an unpaid order is cancelled rather than refunded', () => {
  assert.equal(canTransitionOrder('pending_verification', 'refunded'), false)
  assert.equal(canTransitionOrder('awaiting_payment', 'refunded'), false)
  assert.equal(canTransitionOrder('payment_failed', 'refunded'), false)
})

test('a cancelled order cannot be paid', () => {
  assert.equal(canTransitionOrder('cancelled', 'paid'), false)
})

test('a cancelled order goes nowhere', () => {
  assert.deepEqual(ORDER_STATUS_TRANSITIONS.cancelled, [])
})

test('transition returns the next status', () => {
  assert.deepEqual(transitionOrder('awaiting_payment', 'paid'), { outcome: 'allowed', status: 'paid' })
})

test('a paid order cannot be paid twice', () => {
  assert.deepEqual(
    transitionOrder('paid', 'paid'),
    refused('illegal_transition', { status_from: 'paid', status_to: 'paid' }),
  )
})

test('orderMovedTo returns the status for a legal move', () => {
  assert.equal(orderMovedTo('awaiting_payment', 'paid'), 'paid')
})

test('orderMovedTo throws for a move the table does not allow', () => {
  assert.throws(
    () => orderMovedTo('paid', 'paid'),
    (error: unknown) =>
      error instanceof BrokenContractError &&
      error.reason === 'illegal_transition' &&
      error.message === 'An order cannot move from paid to paid.' &&
      JSON.stringify(error.data) === JSON.stringify({ status_from: 'paid', status_to: 'paid' }),
  )
})

test('orderTransitionRefusalCopy words the illegal move from the refusal data', () => {
  const refusal = refused('illegal_transition', { status_from: 'paid', status_to: 'paid' } as const)

  assert.equal(orderTransitionRefusalCopy(refusal), 'An order cannot move from paid to paid.')
})

test('orderTransitionRefusalCopy renders the refusal data, not a status a route read before the race', () => {
  // The action's refusal carries the status as of the write; the route's earlier
  // row read is stale by the time a concurrent move lands first.
  const routeReadBeforeTheRace = 'awaiting_payment'
  const refusal = refused('illegal_transition', { status_from: 'paid', status_to: 'shipped' } as const)

  const sentence = orderTransitionRefusalCopy(refusal)

  assert.match(sentence, /paid/)
  assert.doesNotMatch(sentence, new RegExp(routeReadBeforeTheRace))
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

test('an order whose fulfillments are all reversed is refunded', () => {
  assert.equal(orderStatusFromFulfillments(['declined']), 'refunded')
  assert.equal(orderStatusFromFulfillments(['refunded']), 'refunded')
  assert.equal(orderStatusFromFulfillments(['declined', 'refunded']), 'refunded')
})

test('a mixed order rolls up from its live fulfillments only', () => {
  assert.equal(orderStatusFromFulfillments(['shipped', 'declined']), 'shipped')
  assert.equal(orderStatusFromFulfillments(['delivered', 'refunded']), 'delivered')
  assert.equal(orderStatusFromFulfillments(['awaiting_shipment', 'declined']), 'paid')
  assert.equal(orderStatusFromFulfillments(['shipped', 'awaiting_shipment', 'declined']), 'partially_shipped')
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

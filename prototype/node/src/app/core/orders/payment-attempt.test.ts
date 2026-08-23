import { test } from 'node:test'
import assert from 'node:assert/strict'
import { TransitionError } from '../transition-error.ts'
import { paymentAttemptFor, settledFulfillments, isPaidAttempt } from './payment-attempt.ts'
import { decideCard } from '../payments/fake-card.ts'
import type { OrderStatus } from './order-status.ts'

const NOW = new Date('2026-08-20T10:00:00Z')

function attemptWith(cardNumber: string, status: OrderStatus) {
  return paymentAttemptFor({ status, decision: decideCard(cardNumber), now: NOW })
}

test('an approved card pays the order', () => {
  const attempt = attemptWith('4242424242424242', 'awaiting_payment')

  assert.equal(attempt.orderStatus, 'paid')
  assert.equal(isPaidAttempt(attempt), true)
  assert.equal(attempt.paymentStatus, 'approved')
  assert.equal(attempt.finalizedAt, NOW)
})

test('an approved card keeps the stock placement took', () => {
  assert.equal(attemptWith('4242424242424242', 'awaiting_payment').stockChange, 'keep')
})

test('a declined card fails the payment and finalizes nothing', () => {
  const attempt = attemptWith('4000000000000002', 'awaiting_payment')

  assert.equal(attempt.orderStatus, 'payment_failed')
  assert.equal(isPaidAttempt(attempt), false)
  assert.equal(attempt.paymentStatus, 'declined')
  assert.equal(attempt.declineReason, 'generic_decline')
  assert.equal(attempt.finalizedAt, null)
})

test('a declined card hands the stock back', () => {
  assert.equal(attemptWith('4000000000000002', 'awaiting_payment').stockChange, 'restore')
})

test('a retry claims the stock again', () => {
  assert.equal(attemptWith('4242424242424242', 'payment_failed').stockChange, 'take')
})

test('it keeps only the last four digits', () => {
  assert.equal(attemptWith('4242 4242 4242 4242', 'awaiting_payment').cardLastFour, '4242')
})

test('it refuses to charge an order that cannot be paid', () => {
  assert.throws(() => attemptWith('4242424242424242', 'paid'), TransitionError)
})

test('a paid attempt settles every fulfillment', () => {
  const attempt = attemptWith('4242424242424242', 'awaiting_payment')

  assert.deepEqual(settledFulfillments(attempt, ['first', 'second']), ['first', 'second'])
})

test('a failed attempt settles nothing', () => {
  const attempt = attemptWith('4000000000000002', 'awaiting_payment')

  assert.deepEqual(settledFulfillments(attempt, ['first', 'second']), [])
})

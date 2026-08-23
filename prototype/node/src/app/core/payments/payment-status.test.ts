import { test } from 'node:test'
import assert from 'node:assert/strict'
import { PAYMENT_STATUSES, paymentStatusFromCardDecision } from './payment-status.ts'
import { approvedCard, declinedCard } from './card-decision.ts'

test('PAYMENT_STATUSES names every status', () => {
  assert.deepEqual(PAYMENT_STATUSES, ['approved', 'declined'])
})

test('an approved card records an approved payment', () => {
  assert.equal(paymentStatusFromCardDecision(approvedCard('4242')), 'approved')
})

test('a declined card records a declined payment', () => {
  assert.equal(paymentStatusFromCardDecision(declinedCard('0002', 'generic_decline')), 'declined')
})

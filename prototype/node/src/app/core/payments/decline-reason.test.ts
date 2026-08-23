import { test } from 'node:test'
import assert from 'node:assert/strict'
import { DECLINE_REASONS, DECLINE_MESSAGES, declineMessage } from './decline-reason.ts'

test('DECLINE_REASONS names every reason', () => {
  assert.deepEqual(DECLINE_REASONS, ['generic_decline', 'insufficient_funds', 'invalid_card_number'])
})

test('every reason has a message for the customer', () => {
  assert.deepEqual(Object.keys(DECLINE_MESSAGES).sort(), [...DECLINE_REASONS].sort())
})

test('insufficient funds says so', () => {
  assert.equal(declineMessage('insufficient_funds'), 'Your card has insufficient funds.')
})

test('generic decline says so', () => {
  assert.equal(declineMessage('generic_decline'), 'Your card was declined.')
})

test('an invalid card number says so', () => {
  assert.equal(declineMessage('invalid_card_number'), 'That card number is not valid.')
})

test('it refuses a reason it does not know', () => {
  assert.throws(() => declineMessage('stolen_card' as never))
})

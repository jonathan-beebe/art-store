import { test } from 'node:test'
import assert from 'node:assert/strict'
import { decideCard } from './fake-card.ts'
import { isCardApproved } from './card-decision.ts'

test('the approved number is approved', () => {
  assert.equal(isCardApproved(decideCard('4242424242424242')), true)
})

test('spaces and dashes are ignored', () => {
  assert.equal(isCardApproved(decideCard('4242 4242-4242 4242')), true)
})

test('the generic decline number is declined', () => {
  const decision = decideCard('4000 0000 0000 0002')

  assert.equal(isCardApproved(decision), false)
  assert.equal(decision.declineReason, 'generic_decline')
})

test('the insufficient funds number is declined', () => {
  assert.equal(decideCard('4000 0000 0000 9995').declineReason, 'insufficient_funds')
})

test('any other number is not a valid card', () => {
  assert.equal(decideCard('1234 5678 1234 5678').declineReason, 'invalid_card_number')
})

test('only the last four digits come back', () => {
  assert.equal(decideCard('4000 0000 0000 9995').lastFour, '9995')
})

test('a number shorter than four digits keeps what it has', () => {
  assert.equal(decideCard('12').lastFour, '12')
})

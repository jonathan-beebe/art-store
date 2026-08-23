import { test } from 'node:test'
import assert from 'node:assert/strict'
import { approvedCard, declinedCard, isCardApproved } from './card-decision.ts'

test('an approved decision carries no reason', () => {
  const decision = approvedCard('4242')

  assert.equal(isCardApproved(decision), true)
  assert.equal(decision.declineReason, null)
  assert.equal(decision.lastFour, '4242')
})

test('a declined decision carries the reason', () => {
  const decision = declinedCard('0002', 'generic_decline')

  assert.equal(isCardApproved(decision), false)
  assert.equal(decision.declineReason, 'generic_decline')
})

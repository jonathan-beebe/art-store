import { test } from 'node:test'
import assert from 'node:assert/strict'
import { holdMovement, payoutMovement, refundMovement, releaseMovement } from './ledger-movement.ts'
import { cents } from '../money.ts'

test('a hold adds the net to escrow', () => {
  const movement = holdMovement(cents(40_500))

  assert.equal(movement.entryType, 'held')
  assert.equal(movement.amountCents, 40_500)
})

test('a release adds the net to what is available', () => {
  const movement = releaseMovement(cents(40_500))

  assert.equal(movement.entryType, 'released')
  assert.equal(movement.amountCents, 40_500)
})

test('a payout leaves the ledger entry negative', () => {
  const movement = payoutMovement(cents(40_500))

  assert.equal(movement.entryType, 'paid_out')
  assert.equal(movement.amountCents, -40_500)
})

test('a payout of zero stays a positive zero', () => {
  const movement = payoutMovement(cents(0))

  assert.equal(Object.is(movement.amountCents, -0), false)
})

test('a refund leaves the ledger entry negative', () => {
  const movement = refundMovement(cents(40_500))

  assert.equal(movement.entryType, 'refunded')
  assert.equal(movement.amountCents, -40_500)
})

test('a refund of zero stays a positive zero', () => {
  const movement = refundMovement(cents(0))

  assert.equal(Object.is(movement.amountCents, -0), false)
})

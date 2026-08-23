import { test } from 'node:test'
import assert from 'node:assert/strict'
import { holdMovement, releaseMovement, payoutMovement } from './ledger-movement.ts'

test('a hold adds the net to escrow', () => {
  const movement = holdMovement(40_500)

  assert.equal(movement.entryType, 'held')
  assert.equal(movement.amountCents, 40_500)
})

test('a release adds the net to what is available', () => {
  const movement = releaseMovement(40_500)

  assert.equal(movement.entryType, 'released')
  assert.equal(movement.amountCents, 40_500)
})

test('a payout leaves the ledger entry negative', () => {
  const movement = payoutMovement(40_500)

  assert.equal(movement.entryType, 'paid_out')
  assert.equal(movement.amountCents, -40_500)
})

test('a payout of zero stays a positive zero', () => {
  const movement = payoutMovement(0)

  assert.equal(Object.is(movement.amountCents, -0), false)
})

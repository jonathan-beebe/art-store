import { test } from 'node:test'
import assert from 'node:assert/strict'
import { ledgerBalance, isPayable } from './ledger-balance.ts'
import { holdMovement, releaseMovement, payoutMovement } from './ledger-movement.ts'

function holdAndRelease(cents: number) {
  return [holdMovement(cents), releaseMovement(cents)]
}

test('an empty ledger owes nothing', () => {
  const balance = ledgerBalance([])

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 0)
  assert.equal(balance.paidOutCents, 0)
  assert.equal(isPayable(balance), false)
})

test('a hold waits on delivery', () => {
  const balance = ledgerBalance([holdMovement(40_500)])

  assert.equal(balance.heldCents, 40_500)
  assert.equal(balance.availableCents, 0)
  assert.equal(isPayable(balance), false)
})

test('a release moves the hold to available', () => {
  const balance = ledgerBalance(holdAndRelease(40_500))

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 40_500)
  assert.equal(isPayable(balance), true)
})

test('a payout empties what was available', () => {
  const movements = [...holdAndRelease(40_500), payoutMovement(40_500)]
  const balance = ledgerBalance(movements)

  assert.equal(balance.availableCents, 0)
  assert.equal(balance.paidOutCents, 40_500)
  assert.equal(isPayable(balance), false)
})

test('it folds a ledger that holds and releases more than once', () => {
  const movements = [...holdAndRelease(40_500), holdMovement(9000)]
  const balance = ledgerBalance(movements)

  assert.equal(balance.heldCents, 9000)
  assert.equal(balance.availableCents, 40_500)
})

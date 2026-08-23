import { test } from 'node:test'
import assert from 'node:assert/strict'
import { ledgerBalance, ledgerBalancesBySeller, isPayable, type SellerLedgerMovement } from './ledger-balance.ts'
import { holdMovement, releaseMovement, payoutMovement } from './ledger-movement.ts'

function holdAndRelease(cents: number) {
  return [holdMovement(cents), releaseMovement(cents)]
}

function forSeller(sellerId: number, movement: ReturnType<typeof holdMovement>): SellerLedgerMovement {
  return { ...movement, sellerId }
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

test('ledgerBalancesBySeller folds an empty ledger to no sellers', () => {
  assert.deepEqual(ledgerBalancesBySeller([]), new Map())
})

test('ledgerBalancesBySeller keeps a seller with only held money out of available', () => {
  const balances = ledgerBalancesBySeller([forSeller(1, holdMovement(40_500))])

  assert.equal(balances.get(1)?.heldCents, 40_500)
  assert.equal(balances.get(1)?.availableCents, 0)
  assert.equal(isPayable(balances.get(1)!), false)
})

test('ledgerBalancesBySeller folds each seller from their own movements only', () => {
  const movements = [
    forSeller(1, holdMovement(40_500)),
    forSeller(1, releaseMovement(40_500)),
    forSeller(2, holdMovement(9_000)),
  ]

  const balances = ledgerBalancesBySeller(movements)

  assert.equal(balances.get(1)?.availableCents, 40_500)
  assert.equal(balances.get(2)?.heldCents, 9_000)
  assert.equal(balances.get(2)?.availableCents, 0)
})

test('ledgerBalancesBySeller has no entry for a seller with no movements', () => {
  const balances = ledgerBalancesBySeller([forSeller(1, holdMovement(1_000))])

  assert.equal(balances.has(2), false)
})

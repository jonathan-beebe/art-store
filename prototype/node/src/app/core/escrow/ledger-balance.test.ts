import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { ledgerBalance, ledgerBalancesBySeller, isPayable, type SellerLedgerMovement } from './ledger-balance.ts'
import { holdMovement, releaseMovement, payoutMovement } from './ledger-movement.ts'
import type { Cents } from '../money.ts'
import { cents } from '../money.ts'
import type { SellerId } from '../ids/entity-ids.ts'

function holdAndRelease(amount: Cents) {
  return [holdMovement(amount), releaseMovement(amount)]
}

function forSeller(sellerId: SellerId, movement: ReturnType<typeof holdMovement>): SellerLedgerMovement {
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
  const balance = ledgerBalance([holdMovement(cents(40_500))])

  assert.equal(balance.heldCents, 40_500)
  assert.equal(balance.availableCents, 0)
  assert.equal(isPayable(balance), false)
})

test('a release moves the hold to available', () => {
  const balance = ledgerBalance(holdAndRelease(cents(40_500)))

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 40_500)
  assert.equal(isPayable(balance), true)
})

test('a payout empties what was available', () => {
  const movements = [...holdAndRelease(cents(40_500)), payoutMovement(cents(40_500))]
  const balance = ledgerBalance(movements)

  assert.equal(balance.availableCents, 0)
  assert.equal(balance.paidOutCents, 40_500)
  assert.equal(isPayable(balance), false)
})

test('it folds a ledger that holds and releases more than once', () => {
  const movements = [...holdAndRelease(cents(40_500)), holdMovement(cents(9000))]
  const balance = ledgerBalance(movements)

  assert.equal(balance.heldCents, 9000)
  assert.equal(balance.availableCents, 40_500)
})

test('ledgerBalancesBySeller folds an empty ledger to no sellers', () => {
  assert.deepEqual(ledgerBalancesBySeller([]), new Map())
})

test('ledgerBalancesBySeller keeps a seller with only held money out of available', () => {
  const balances = ledgerBalancesBySeller([forSeller(fixtureId('sel', 1), holdMovement(cents(40_500)))])

  assert.equal(balances.get(fixtureId('sel', 1))?.heldCents, 40_500)
  assert.equal(balances.get(fixtureId('sel', 1))?.availableCents, 0)
  assert.equal(isPayable(balances.get(fixtureId('sel', 1))!), false)
})

test('ledgerBalancesBySeller folds each seller from their own movements only', () => {
  const movements = [
    forSeller(fixtureId('sel', 1), holdMovement(cents(40_500))),
    forSeller(fixtureId('sel', 1), releaseMovement(cents(40_500))),
    forSeller(fixtureId('sel', 2), holdMovement(cents(9_000))),
  ]

  const balances = ledgerBalancesBySeller(movements)

  assert.equal(balances.get(fixtureId('sel', 1))?.availableCents, 40_500)
  assert.equal(balances.get(fixtureId('sel', 2))?.heldCents, 9_000)
  assert.equal(balances.get(fixtureId('sel', 2))?.availableCents, 0)
})

test('ledgerBalancesBySeller has no entry for a seller with no movements', () => {
  const balances = ledgerBalancesBySeller([forSeller(fixtureId('sel', 1), holdMovement(cents(1_000)))])

  assert.equal(balances.has(fixtureId('sel', 2)), false)
})

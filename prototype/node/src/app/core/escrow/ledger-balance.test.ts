import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import {
  ledgerBalance,
  ledgerBalancesBySeller,
  isPayable,
  type BalanceMovement,
  type SellerLedgerMovement,
} from './ledger-balance.ts'
import { holdMovement, payoutMovement, refundMovement, releaseMovement } from './ledger-movement.ts'
import type { LedgerMovement } from './ledger-movement.ts'
import type { Cents } from '../money.ts'
import { cents } from '../money.ts'
import type { FulfillmentId, SellerId } from '../ids/entity-ids.ts'

const SALE: FulfillmentId = fixtureId('ful', 1)
const OTHER_SALE: FulfillmentId = fixtureId('ful', 2)

/** A movement as the fold reads it: attached to the sale it belongs to. */
function on(fulfillmentId: FulfillmentId, movement: LedgerMovement): BalanceMovement {
  return { ...movement, fulfillmentId }
}

/** A payout belongs to no single sale, which is what the null column says. */
function payout(amount: Cents): BalanceMovement {
  return { ...payoutMovement(amount), fulfillmentId: null }
}

function holdAndRelease(amount: Cents): readonly BalanceMovement[] {
  return [on(SALE, holdMovement(amount)), on(SALE, releaseMovement(amount))]
}

function forSeller(sellerId: SellerId, movement: BalanceMovement): SellerLedgerMovement {
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
  const balance = ledgerBalance([on(SALE, holdMovement(cents(40_500)))])

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
  const movements = [...holdAndRelease(cents(40_500)), payout(cents(40_500))]
  const balance = ledgerBalance(movements)

  assert.equal(balance.availableCents, 0)
  assert.equal(balance.paidOutCents, 40_500)
  assert.equal(isPayable(balance), false)
})

test('it folds a ledger that holds and releases more than once', () => {
  const movements = [...holdAndRelease(cents(40_500)), on(OTHER_SALE, holdMovement(cents(9000)))]
  const balance = ledgerBalance(movements)

  assert.equal(balance.heldCents, 9000)
  assert.equal(balance.availableCents, 40_500)
})

test('ledgerBalancesBySeller folds an empty ledger to no sellers', () => {
  assert.deepEqual(ledgerBalancesBySeller([]), new Map())
})

test('ledgerBalancesBySeller keeps a seller with only held money out of available', () => {
  const balances = ledgerBalancesBySeller([forSeller(fixtureId('sel', 1), on(SALE, holdMovement(cents(40_500))))])

  assert.equal(balances.get(fixtureId('sel', 1))?.heldCents, 40_500)
  assert.equal(balances.get(fixtureId('sel', 1))?.availableCents, 0)
  assert.equal(isPayable(balances.get(fixtureId('sel', 1))!), false)
})

test('ledgerBalancesBySeller folds each seller from their own movements only', () => {
  const movements = [
    forSeller(fixtureId('sel', 1), on(SALE, holdMovement(cents(40_500)))),
    forSeller(fixtureId('sel', 1), on(SALE, releaseMovement(cents(40_500)))),
    forSeller(fixtureId('sel', 2), on(OTHER_SALE, holdMovement(cents(9_000)))),
  ]

  const balances = ledgerBalancesBySeller(movements)

  assert.equal(balances.get(fixtureId('sel', 1))?.availableCents, 40_500)
  assert.equal(balances.get(fixtureId('sel', 2))?.heldCents, 9_000)
  assert.equal(balances.get(fixtureId('sel', 2))?.availableCents, 0)
})

test('ledgerBalancesBySeller has no entry for a seller with no movements', () => {
  const balances = ledgerBalancesBySeller([forSeller(fixtureId('sel', 1), on(SALE, holdMovement(cents(1_000))))])

  assert.equal(balances.has(fixtureId('sel', 2)), false)
})

// The three refund timings docs/alignment.md §4.2 tabulates, each folded from
// the entries the corresponding action writes.

test('a refund before release takes the hold back off the seller', () => {
  const balance = ledgerBalance([
    on(SALE, holdMovement(cents(40_500))),
    on(SALE, refundMovement(cents(40_500))),
  ])

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 0)
  assert.equal(balance.paidOutCents, 0)
  assert.equal(isPayable(balance), false)
})

test('a refund after release drops the available balance', () => {
  const balance = ledgerBalance([
    ...holdAndRelease(cents(40_500)),
    on(SALE, refundMovement(cents(40_500))),
  ])

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 0)
  assert.equal(balance.paidOutCents, 0)
})

test('a refund after release leaves the seller\'s other released money alone', () => {
  const balance = ledgerBalance([
    ...holdAndRelease(cents(40_500)),
    on(OTHER_SALE, holdMovement(cents(9_000))),
    on(OTHER_SALE, releaseMovement(cents(9_000))),
    on(SALE, refundMovement(cents(40_500))),
  ])

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 9_000)
  assert.equal(isPayable(balance), true)
})

test('a refund after payout leaves the negative against the seller', () => {
  const balance = ledgerBalance([
    ...holdAndRelease(cents(40_500)),
    payout(cents(40_500)),
    on(SALE, refundMovement(cents(40_500))),
  ])

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, -40_500)
  assert.equal(balance.paidOutCents, 40_500)
  assert.equal(isPayable(balance), false)
})

test('a later sale nets against a carried negative before it is payable', () => {
  const settled = [
    ...holdAndRelease(cents(40_500)),
    payout(cents(40_500)),
    on(SALE, refundMovement(cents(40_500))),
  ]

  const halfCovered = ledgerBalance([
    ...settled,
    on(OTHER_SALE, holdMovement(cents(9_000))),
    on(OTHER_SALE, releaseMovement(cents(9_000))),
  ])

  assert.equal(halfCovered.availableCents, -31_500)
  assert.equal(isPayable(halfCovered), false)
})

test('a refund on a sale that never released comes out of held, not available', () => {
  const balance = ledgerBalance([
    ...holdAndRelease(cents(40_500)),
    on(OTHER_SALE, holdMovement(cents(9_000))),
    on(OTHER_SALE, refundMovement(cents(9_000))),
  ])

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 40_500)
  assert.equal(isPayable(balance), true)
})

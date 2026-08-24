import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { cartTotals, checkoutTotals } from './cart-totals.ts'
import type { CartLine } from './cart-line.ts'
import type { Cents } from '../money.ts'
import { cents } from '../money.ts'
import type { SellerId } from '../ids/entity-ids.ts'

function line(sellerId: SellerId, unitPriceCents: Cents, quantity: number): CartLine {
  return { sellerId, unitPriceCents, quantity }
}

test('it counts every item', () => {
  assert.equal(cartTotals([line(fixtureId('sel', 1), cents(4500), 2), line(fixtureId('sel', 1), cents(1000), 1)]).itemCount, 3)
})

test('it adds every line', () => {
  assert.equal(cartTotals([line(fixtureId('sel', 1), cents(4500), 2), line(fixtureId('sel', 2), cents(1000), 1)]).subtotalCents, 10_000)
})

test('it splits the subtotal by seller', () => {
  const totals = cartTotals([line(fixtureId('sel', 2), cents(1000), 1), line(fixtureId('sel', 1), cents(4500), 2), line(fixtureId('sel', 1), cents(500), 1)])

  assert.deepEqual(totals.subtotalsBySeller, [
    { sellerId: fixtureId('sel', 1), subtotalCents: 9500 },
    { sellerId: fixtureId('sel', 2), subtotalCents: 1000 },
  ])
})

test('it orders the sellers by id', () => {
  const totals = cartTotals([line(fixtureId('sel', 9), cents(1000), 1), line(fixtureId('sel', 3), cents(1000), 1)])

  assert.deepEqual(
    totals.subtotalsBySeller.map((subtotal) => subtotal.sellerId),
    [fixtureId('sel', 3), fixtureId('sel', 9)],
  )
})

test('an empty cart totals nothing', () => {
  const totals = cartTotals([])

  assert.equal(totals.itemCount, 0)
  assert.equal(totals.subtotalCents, 0)
  assert.deepEqual(totals.subtotalsBySeller, [])
})

test('checkout refuses an empty cart', () => {
  assert.throws(() => checkoutTotals([]), RangeError)
})

test('checkout totals a cart that has something in it', () => {
  assert.equal(checkoutTotals([line(fixtureId('sel', 1), cents(4500), 1)]).subtotalCents, 4500)
})

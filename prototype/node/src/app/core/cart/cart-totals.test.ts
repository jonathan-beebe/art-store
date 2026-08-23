import { test } from 'node:test'
import assert from 'node:assert/strict'
import { cartTotals, checkoutTotals } from './cart-totals.ts'
import type { CartLine } from './cart-line.ts'

function line(sellerId: number, unitPriceCents: number, quantity: number): CartLine {
  return { sellerId, unitPriceCents, quantity }
}

test('it counts every item', () => {
  assert.equal(cartTotals([line(1, 4500, 2), line(1, 1000, 1)]).itemCount, 3)
})

test('it adds every line', () => {
  assert.equal(cartTotals([line(1, 4500, 2), line(2, 1000, 1)]).subtotalCents, 10_000)
})

test('it splits the subtotal by seller', () => {
  const totals = cartTotals([line(2, 1000, 1), line(1, 4500, 2), line(1, 500, 1)])

  assert.deepEqual(totals.subtotalsBySeller, [
    { sellerId: 1, subtotalCents: 9500 },
    { sellerId: 2, subtotalCents: 1000 },
  ])
})

test('it orders the sellers by id', () => {
  const totals = cartTotals([line(9, 1000, 1), line(3, 1000, 1)])

  assert.deepEqual(
    totals.subtotalsBySeller.map((subtotal) => subtotal.sellerId),
    [3, 9],
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
  assert.equal(checkoutTotals([line(1, 4500, 1)]).subtotalCents, 4500)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { quantityWithinStock } from './cart-quantity.ts'

test('it takes what was asked for when the stock covers it', () => {
  assert.equal(quantityWithinStock({ requested: 2, available: 3 }), 2)
})

test('it stops at what is left', () => {
  assert.equal(quantityWithinStock({ requested: 5, available: 3 }), 3)
})

test('it holds at least one of a listing', () => {
  assert.throws(() => quantityWithinStock({ requested: 0, available: 3 }), RangeError)
})

test('it refuses a sold-out listing', () => {
  assert.throws(() => quantityWithinStock({ requested: 1, available: 0 }), RangeError)
})

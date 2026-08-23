import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createCartLine, cartLineTotal } from './cart-line.ts'

test('a line totals its unit price', () => {
  const line = createCartLine({ sellerId: 1, unitPriceCents: 4500, quantity: 3 })

  assert.equal(cartLineTotal(line), 13_500)
})

test('a line covers at least one item', () => {
  assert.throws(
    () => createCartLine({ sellerId: 1, unitPriceCents: 4500, quantity: 0 }),
    RangeError,
  )
})

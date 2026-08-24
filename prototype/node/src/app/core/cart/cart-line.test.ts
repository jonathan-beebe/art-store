import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { createCartLine, cartLineTotal } from './cart-line.ts'
import { cents } from '../money.ts'

test('a line totals its unit price', () => {
  const line = createCartLine({ sellerId: fixtureId('sel', 1), unitPriceCents: cents(4500), quantity: 3 })

  assert.equal(cartLineTotal(line), 13_500)
})

test('a line covers at least one item', () => {
  assert.throws(
    () => createCartLine({ sellerId: fixtureId('sel', 1), unitPriceCents: cents(4500), quantity: 0 }),
    RangeError,
  )
})

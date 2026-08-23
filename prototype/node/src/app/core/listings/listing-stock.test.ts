import { test } from 'node:test'
import assert from 'node:assert/strict'
import { stockAfterSale, stockAfterRestock, stockAfter } from './listing-stock.ts'

test('a sale takes the quantity it asks for', () => {
  const stock = stockAfterSale({ quantity: 3, status: 'for_sale', sold: 2 })
  assert.equal(stock.quantity, 1)
  assert.equal(stock.status, 'for_sale')
})

test('the last of a listing marks it sold', () => {
  const stock = stockAfterSale({ quantity: 1, status: 'for_sale', sold: 1 })
  assert.equal(stock.quantity, 0)
  assert.equal(stock.status, 'sold')
})

test('a sale refuses to take more than is left', () => {
  assert.throws(() => stockAfterSale({ quantity: 1, status: 'for_sale', sold: 2 }), RangeError)
})

test('a sale refuses a listing that is not for sale', () => {
  assert.throws(() => stockAfterSale({ quantity: 1, status: 'draft', sold: 1 }), RangeError)
})

test('a sale covers at least one item', () => {
  assert.throws(() => stockAfterSale({ quantity: 3, status: 'for_sale', sold: 0 }), RangeError)
})

test('a restock puts a sold listing back on the storefront', () => {
  const stock = stockAfterRestock({ quantity: 0, status: 'sold', restored: 1 })
  assert.equal(stock.quantity, 1)
  assert.equal(stock.status, 'for_sale')
})

test('a restock leaves a listing that is still for sale alone', () => {
  const stock = stockAfterRestock({ quantity: 2, status: 'for_sale', restored: 1 })
  assert.equal(stock.quantity, 3)
  assert.equal(stock.status, 'for_sale')
})

test('a restock covers at least one item', () => {
  assert.throws(() => stockAfterRestock({ quantity: 0, status: 'sold', restored: 0 }), RangeError)
})

test('take sells the items', () => {
  const stock = stockAfter('take', { quantity: 2, status: 'for_sale', items: 1 })
  assert.equal(stock.quantity, 1)
})

test('restore hands the items back', () => {
  const stock = stockAfter('restore', { quantity: 0, status: 'sold', items: 1 })
  assert.equal(stock.quantity, 1)
  assert.equal(stock.status, 'for_sale')
})

test('keep leaves the listing as it is', () => {
  const stock = stockAfter('keep', { quantity: 2, status: 'for_sale', items: 1 })
  assert.equal(stock.quantity, 2)
  assert.equal(stock.status, 'for_sale')
})

test('it refuses a change it does not know', () => {
  assert.throws(
    () => stockAfter('reserve' as never, { quantity: 2, status: 'for_sale', items: 1 }),
    TypeError,
  )
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { platformFee, sellerNet } from './fee.ts'

test('platformFee takes a tenth', () => {
  assert.equal(platformFee(45_000), 4500)
})

test('sellerNet keeps the rest', () => {
  assert.equal(sellerNet(45_000), 40_500)
})

test('the fee and the net add back up to the subtotal', () => {
  const subtotal = 4999

  assert.equal(platformFee(subtotal) + sellerNet(subtotal), subtotal)
})

test('half a cent rounds away from zero', () => {
  assert.equal(platformFee(45), 5)
})

test('nothing owes nothing', () => {
  assert.equal(platformFee(0), 0)
})

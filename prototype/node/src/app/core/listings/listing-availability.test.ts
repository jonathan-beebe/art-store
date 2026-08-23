import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isOnStorefront, isPurchasable } from './listing-availability.ts'

test('a for_sale listing is on the storefront', () => {
  assert.equal(isOnStorefront('for_sale', false), true)
})

test('a sold listing keeps its page', () => {
  assert.equal(isOnStorefront('sold', false), true)
})

test('a draft listing was never public', () => {
  assert.equal(isOnStorefront('draft', false), false)
})

test('an archived listing leaves the storefront', () => {
  assert.equal(isOnStorefront('archived', false), false)
})

test('an active removal takes a for_sale listing off the storefront', () => {
  assert.equal(isOnStorefront('for_sale', true), false)
})

test('a for_sale listing in stock is purchasable', () => {
  assert.equal(isPurchasable('for_sale', 1, false), true)
})

test('a for_sale listing with no stock is not purchasable', () => {
  assert.equal(isPurchasable('for_sale', 0, false), false)
})

test('a sold listing is not purchasable', () => {
  assert.equal(isPurchasable('sold', 3, false), false)
})

test('a removed listing is not purchasable even in stock', () => {
  assert.equal(isPurchasable('for_sale', 3, true), false)
})

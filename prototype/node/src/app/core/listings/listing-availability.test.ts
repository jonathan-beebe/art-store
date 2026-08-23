import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  BROWSABLE_STATUSES,
  STOREFRONT_STATUSES,
  isOnStorefront,
  isPurchasable,
} from './listing-availability.ts'
import { LISTING_STATUSES } from './listing-status.ts'

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

test('a status is on the storefront exactly when STOREFRONT_STATUSES names it', () => {
  for (const status of LISTING_STATUSES) {
    assert.equal(isOnStorefront(status, false), STOREFRONT_STATUSES.includes(status))
  }
})

test('a browsable status in stock is purchasable', () => {
  for (const status of BROWSABLE_STATUSES) {
    assert.equal(isPurchasable(status, 1, false), true)
  }
})

test('everything browsable is on the storefront', () => {
  for (const status of BROWSABLE_STATUSES) {
    assert.equal(STOREFRONT_STATUSES.includes(status), true)
  }
})

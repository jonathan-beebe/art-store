import { test } from 'node:test'
import assert from 'node:assert/strict'
import { shopPage } from './shop-page.ts'

test('it carries the shared helpers and defaults alongside the page data', () => {
  const page = shopPage({ title: 'Cart' })

  assert.equal(page.title, 'Cart')
  assert.equal(page.searchTerm, '')
  assert.equal(typeof page.formatCents, 'function')
  assert.equal(typeof page.statusLabel, 'function')
  assert.equal(typeof page.dayLabel, 'function')
  assert.equal(typeof page.shopName, 'function')
  assert.equal(typeof page.listingImageSource, 'function')
})

test('the page data overrides a default of the same name', () => {
  const page = shopPage({ title: 'Search', searchTerm: 'harbour' })

  assert.equal(page.searchTerm, 'harbour')
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { pageViewSite } from './page-view-site.ts'

test('a portal prefix names its own site', () => {
  assert.equal(pageViewSite('/seller'), 'seller')
  assert.equal(pageViewSite('/seller/listings/:id'), 'seller')
  assert.equal(pageViewSite('/admin'), 'admin')
  assert.equal(pageViewSite('/admin/customers/:id'), 'admin')
})

test('everything else is the storefront', () => {
  assert.equal(pageViewSite('/'), 'shop')
  assert.equal(pageViewSite('/art/:slug'), 'shop')
  assert.equal(pageViewSite('/auth/magic/:token'), 'shop')
})

test('a path that merely starts with the letters of a prefix is not that portal', () => {
  assert.equal(pageViewSite('/sellers-guide'), 'shop')
  assert.equal(pageViewSite('/administration'), 'shop')
})

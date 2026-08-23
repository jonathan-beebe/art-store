import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sellerListingTransitions } from './listing-transitions.ts'

test('a draft offers the transitions the lifecycle allows', () => {
  assert.deepEqual(sellerListingTransitions('draft', false), ['for_sale', 'archived'])
})

test('a sold listing with no active removal may return to sale', () => {
  assert.deepEqual(sellerListingTransitions('sold', false), ['for_sale'])
})

test('an active removal takes for_sale off the table', () => {
  assert.deepEqual(sellerListingTransitions('sold', true), [])
})

test('an active removal leaves an unrelated transition alone', () => {
  assert.deepEqual(sellerListingTransitions('draft', true), ['archived'])
})

test('a listing with no further transitions offers none either way', () => {
  assert.deepEqual(sellerListingTransitions('archived', false), [])
  assert.deepEqual(sellerListingTransitions('archived', true), [])
})

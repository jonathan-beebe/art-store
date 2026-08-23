import { test } from 'node:test'
import assert from 'node:assert/strict'
import { LISTING_EVENT_TYPES } from './listing-event-type.ts'

test('LISTING_EVENT_TYPES names every event a listing records', () => {
  assert.deepEqual(LISTING_EVENT_TYPES, ['view', 'favorite', 'unfavorite', 'cart_add'])
})

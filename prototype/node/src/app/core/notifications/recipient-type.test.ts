import { test } from 'node:test'
import assert from 'node:assert/strict'
import { RECIPIENT_TYPES } from './recipient-type.ts'

test('every recipient across the three sites is named', () => {
  assert.deepEqual(RECIPIENT_TYPES, ['seller', 'customer', 'admin'])
})

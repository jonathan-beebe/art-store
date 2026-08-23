import { test } from 'node:test'
import assert from 'node:assert/strict'
import { STOCK_CHANGES } from './stock-change.ts'

test('every change an order makes to stock is named', () => {
  assert.deepEqual(STOCK_CHANGES, ['take', 'restore', 'keep'])
})

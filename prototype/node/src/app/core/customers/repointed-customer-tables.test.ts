import { test } from 'node:test'
import assert from 'node:assert/strict'
import { REPOINTED_CUSTOMER_TABLES } from './repointed-customer-tables.ts'

test('names exactly the four repointed tables, in order', () => {
  assert.deepEqual(
    REPOINTED_CUSTOMER_TABLES.map((entry) => entry.table),
    ['orders', 'listing_events', 'notifications', 'conversations'],
  )
})

test('every entry repoints customer_id', () => {
  for (const entry of REPOINTED_CUSTOMER_TABLES) {
    assert.equal(entry.column, 'customer_id')
  }
})

test('carts and favorites are not repointed', () => {
  const tables = REPOINTED_CUSTOMER_TABLES.map((entry) => entry.table)
  assert.equal(tables.includes('carts'), false)
  assert.equal(tables.includes('favorites'), false)
})

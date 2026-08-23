import { test } from 'node:test'
import assert from 'node:assert/strict'
import { REPOINTED_CUSTOMER_TABLES } from './repointed-customer-tables.ts'

test('names exactly the four repointed tables, in order', () => {
  assert.deepEqual(REPOINTED_CUSTOMER_TABLES, [
    'orders',
    'listingEvents',
    'notifications',
    'conversations',
  ])
})

test('carts and favorites are not repointed', () => {
  const tables: readonly string[] = REPOINTED_CUSTOMER_TABLES

  assert.equal(tables.includes('carts'), false)
  assert.equal(tables.includes('favorites'), false)
})

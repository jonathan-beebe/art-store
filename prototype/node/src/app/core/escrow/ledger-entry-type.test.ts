import { test } from 'node:test'
import assert from 'node:assert/strict'
import { LEDGER_ENTRY_TYPES } from './ledger-entry-type.ts'

test('every step through escrow is named, in order', () => {
  assert.deepEqual(LEDGER_ENTRY_TYPES, ['held', 'released', 'paid_out', 'refunded'])
})

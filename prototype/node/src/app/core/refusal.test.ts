import { test } from 'node:test'
import assert from 'node:assert/strict'
import { refused } from './refusal.ts'

test('a refusal carries the reason and the data behind it', () => {
  assert.deepEqual(refused('card_declined', { order_id: 'ord_1' }), {
    outcome: 'refused',
    reason: 'card_declined',
    data: { order_id: 'ord_1' },
  })
})

test('a refusal built without data carries no data key at all', () => {
  const refusal = refused('expired')

  assert.deepEqual(refusal, { outcome: 'refused', reason: 'expired' })
  assert.ok(!('data' in refusal))
})

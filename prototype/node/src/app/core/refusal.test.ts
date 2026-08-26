import { test } from 'node:test'
import assert from 'node:assert/strict'
import { refused, type IllegalTransition } from './refusal.ts'

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

test('a transition refusal promises its two statuses in the type', () => {
  const refusal: IllegalTransition<'draft' | 'sold'> = refused('illegal_transition', {
    status_from: 'draft',
    status_to: 'sold',
  } as const)

  assert.deepEqual(refusal.data, { status_from: 'draft', status_to: 'sold' })
})

test('a refusal without the promised facts does not compile as a transition refusal', () => {
  // Never run — the assertion is the compile step's.
  const rejectsMissingFacts = () => {
    // @ts-expect-error -- an illegal_transition refusal requires status_from and status_to
    const refusal: IllegalTransition<'draft' | 'sold'> = refused('illegal_transition')

    return refusal
  }

  assert.equal(typeof rejectsMissingFacts, 'function')
})

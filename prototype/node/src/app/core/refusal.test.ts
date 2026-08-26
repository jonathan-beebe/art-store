import { test } from 'node:test'
import assert from 'node:assert/strict'
import { refused, transitionFacts } from './refusal.ts'
import { BrokenContractError } from './defect.ts'

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

test('transitionFacts reads the two statuses out of the refusal data', () => {
  const refusal = refused('illegal_transition', { status_from: 'draft', status_to: 'sold' })

  assert.deepEqual(transitionFacts(refusal), { status_from: 'draft', status_to: 'sold' })
})

test('transitionFacts throws for a refusal with no data', () => {
  const refusal = refused('illegal_transition')

  assert.throws(
    () => transitionFacts(refusal),
    (error: unknown) => error instanceof BrokenContractError && error.reason === 'missing_transition_statuses',
  )
})

test('transitionFacts throws when status_from is not a string', () => {
  const refusal = refused('illegal_transition', { status_from: 1, status_to: 'sold' })

  assert.throws(
    () => transitionFacts(refusal),
    (error: unknown) => error instanceof BrokenContractError && error.reason === 'missing_transition_statuses',
  )
})

test('transitionFacts throws when status_to is not a string', () => {
  const refusal = refused('illegal_transition', { status_from: 'draft', status_to: 1 })

  assert.throws(
    () => transitionFacts(refusal),
    (error: unknown) => error instanceof BrokenContractError && error.reason === 'missing_transition_statuses',
  )
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { TransitionError } from './transition-error.ts'

test('a transition error carries the refusal it was built with', () => {
  const error = new TransitionError('An order cannot move from paid to paid.')

  assert.equal(error.message, 'An order cannot move from paid to paid.')
  assert.equal(error.name, 'TransitionError')
  assert.ok(error instanceof Error)
})

test('a transition error is distinguishable from any other error', () => {
  assert.ok(new TransitionError('nope') instanceof TransitionError)
  assert.ok(!(new Error('nope') instanceof TransitionError))
})

test('a transition error carries the reason and data it was built with', () => {
  const error = new TransitionError('A listing cannot move from archived to for_sale.', {
    reason: 'stale_status',
    data: { listing_id: 'lst_00000000000000000000000001' },
  })

  assert.equal(error.reason, 'stale_status')
  assert.deepEqual(error.data, { listing_id: 'lst_00000000000000000000000001' })
})

test('a plain transition error carries neither a reason nor data', () => {
  const error = new TransitionError('An order cannot move from paid to paid.')

  assert.equal(error.reason, undefined)
  assert.equal(error.data, undefined)
})

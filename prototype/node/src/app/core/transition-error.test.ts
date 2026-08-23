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

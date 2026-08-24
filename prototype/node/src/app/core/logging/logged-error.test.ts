import { test } from 'node:test'
import assert from 'node:assert/strict'
import { describeError, isDomainRefusal } from './logged-error.ts'
import { TransitionError } from '../transition-error.ts'

test('an Error is described by its name, its message, and its stack', () => {
  const described = describeError(new TypeError('not a number'))

  assert.equal(described.type, 'TypeError')
  assert.equal(described.message, 'not a number')
  assert.match(String(described.stack), /TypeError: not a number/)
})

test('a subclass is described by the name it set for itself', () => {
  assert.equal(describeError(new TransitionError('cannot ship twice')).type, 'TransitionError')
})

test('something thrown that is not an Error is still described', () => {
  assert.deepEqual(describeError('just a string'), { type: 'string', message: 'just a string' })
  assert.deepEqual(describeError(42), { type: 'number', message: '42' })
  assert.deepEqual(describeError(null), { type: 'object', message: 'null' })
})

test('a TransitionError is the domain saying no; anything else is a fault', () => {
  assert.equal(isDomainRefusal(new TransitionError('cannot ship twice')), true)
  assert.equal(isDomainRefusal(new Error('the kiln exploded')), false)
  assert.equal(isDomainRefusal('a string'), false)
})

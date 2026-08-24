import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isAcceptableRequestId } from './request-id.ts'

test('a token of letters, digits, underscores, and hyphens is accepted', () => {
  assert.equal(isAcceptableRequestId('req-01J5X3M9_A2K8'), true)
  assert.equal(isAcceptableRequestId('a'), true)
  assert.equal(isAcceptableRequestId('x'.repeat(64)), true)
})

test('an empty or over-long id is refused', () => {
  assert.equal(isAcceptableRequestId(''), false)
  assert.equal(isAcceptableRequestId('x'.repeat(65)), false)
})

test('anything that could break a log line or a header is refused', () => {
  for (const value of ['has space', 'semi;colon', 'new\nline', 'quote"mark', 'slash/es', 'é']) {
    assert.equal(isAcceptableRequestId(value), false, value)
  }
})

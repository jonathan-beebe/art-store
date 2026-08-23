import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseIdParam } from './id-param.ts'

test('a numeric string id parses to its number', () => {
  assert.equal(parseIdParam({ id: '42' }), 42)
})

test('a numeric id parses as itself', () => {
  assert.equal(parseIdParam({ id: 42 }), 42)
})

test('zero is not a positive integer', () => {
  assert.equal(parseIdParam({ id: 0 }), null)
})

test('a negative id is refused', () => {
  assert.equal(parseIdParam({ id: -1 }), null)
})

test('a non-numeric string is refused', () => {
  assert.equal(parseIdParam({ id: 'abc' }), null)
})

test('a missing id key is refused', () => {
  assert.equal(parseIdParam({}), null)
})

test('a non-object is refused', () => {
  assert.equal(parseIdParam('42'), null)
})

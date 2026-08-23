import { test } from 'node:test'
import assert from 'node:assert/strict'
import { toCount } from './count.ts'

test('a number aggregate reads as itself', () => {
  assert.equal(toCount(12), 12)
})

test('a string aggregate reads as a number', () => {
  assert.equal(toCount('12'), 12)
})

test('a bigint aggregate reads as a number', () => {
  assert.equal(toCount(12n), 12)
})

test('a sum over no rows reads as zero', () => {
  assert.equal(toCount(null), 0)
  assert.equal(toCount(undefined), 0)
})

test('a value that is not a count at all is a programmer error', () => {
  assert.throws(() => toCount('twelve'), TypeError)
})

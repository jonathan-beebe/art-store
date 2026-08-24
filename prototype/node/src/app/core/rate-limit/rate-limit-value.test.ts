import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseRateLimit } from './rate-limit-value.ts'

test('a count and a window in seconds', () => {
  assert.deepEqual(parseRateLimit('5/15s', '1/1m'), { ok: true, value: { count: 5, windowSeconds: 15 } })
})

test('a count and a window in minutes', () => {
  assert.deepEqual(parseRateLimit('5/15m', '1/1m'), { ok: true, value: { count: 5, windowSeconds: 900 } })
})

test('a count and a window in hours', () => {
  assert.deepEqual(parseRateLimit('5/1h', '1/1m'), { ok: true, value: { count: 5, windowSeconds: 3600 } })
})

test('off disables the limit', () => {
  assert.deepEqual(parseRateLimit('off', '1/1m'), { ok: true, value: 'off' })
})

test('an unset value falls back to the default', () => {
  assert.deepEqual(parseRateLimit(undefined, '5/15m'), { ok: true, value: { count: 5, windowSeconds: 900 } })
})

test('an unset value falls back even when the default is off', () => {
  assert.deepEqual(parseRateLimit(undefined, 'off'), { ok: true, value: 'off' })
})

const MALFORMED = ['', '5', '5/', '/15m', '0/15m', '-1/15m', '5/0m', '5/-1m', '5/15', '5/15d', '5 / 15m', '5/15M', 'On']

for (const value of MALFORMED) {
  test(`"${value}" is malformed`, () => {
    const result = parseRateLimit(value, '1/1m')

    assert.equal(result.ok, false)
  })
}

test('a non-integer count is malformed', () => {
  assert.equal(parseRateLimit('5.5/15m', '1/1m').ok, false)
})

test('a huge count is malformed rather than overflowing', () => {
  assert.equal(parseRateLimit('99999999999999999999/15m', '1/1m').ok, false)
})

test('a huge window is malformed rather than overflowing', () => {
  assert.equal(parseRateLimit('5/99999999999999999999h', '1/1m').ok, false)
})

test('a malformed value carries a message naming the offending value', () => {
  const result = parseRateLimit('nonsense', '1/1m')

  assert.equal(result.ok, false)
  if (result.ok) throw new Error('expected a malformed result')
  assert.match(result.error, /nonsense/)
})

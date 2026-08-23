import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseAsOfDay } from './payout-day.ts'

const FALLBACK = new Date('2026-08-24T09:00:00.000Z')

test('no value falls back to the caller-given moment', () => {
  assert.equal(parseAsOfDay(undefined, FALLBACK), FALLBACK)
})

test('a day string parses to midnight UTC that day', () => {
  assert.deepEqual(parseAsOfDay('2026-08-17', FALLBACK), new Date('2026-08-17T00:00:00.000Z'))
})

test('a day just after a year boundary parses to the right year', () => {
  assert.deepEqual(parseAsOfDay('2026-01-01', FALLBACK), new Date('2026-01-01T00:00:00.000Z'))
})

test('a day just before a year boundary parses to the right year', () => {
  assert.deepEqual(parseAsOfDay('2025-12-31', FALLBACK), new Date('2025-12-31T00:00:00.000Z'))
})

test('a value that is not YYYY-MM-DD is refused', () => {
  assert.throws(() => parseAsOfDay('last tuesday', FALLBACK), /last tuesday/)
})

test('an empty string is refused rather than treated as no value', () => {
  assert.throws(() => parseAsOfDay('', FALLBACK), /""/)
})

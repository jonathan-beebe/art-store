import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseAsOf } from './parse-as-of.ts'

const FALLBACK = new Date('2026-08-24T09:00:00.000Z')

test('it reads the date the flag names', () => {
  assert.deepEqual(parseAsOf(['--as-of=2026-08-17'], FALLBACK), new Date('2026-08-17'))
})

test('it reads the flag from anywhere on the command line', () => {
  assert.deepEqual(parseAsOf(['--quiet', '--as-of=2026-08-17'], FALLBACK), new Date('2026-08-17'))
})

test('a command line with no flag falls back to the moment the caller gave', () => {
  assert.equal(parseAsOf([], FALLBACK), FALLBACK)
})

test('it refuses a flag that is not a date', () => {
  assert.throws(() => parseAsOf(['--as-of=last tuesday'], FALLBACK), /last tuesday/)
})

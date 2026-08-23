import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fromNullableTimestamp, fromTimestamp, toTimestamp } from './timestamp.ts'

const INSTANT = new Date('2026-08-24T12:00:00.000Z')

test('an instant is stored as ISO-8601 in UTC', () => {
  assert.equal(toTimestamp(INSTANT), '2026-08-24T12:00:00.000Z')
})

test('a stored timestamp reads back as the instant it came from', () => {
  assert.deepEqual(fromTimestamp(toTimestamp(INSTANT)), INSTANT)
})

test('stored timestamps compare in chronological order as text', () => {
  const earlier = toTimestamp(new Date('2026-08-24T11:59:59.999Z'))

  assert.ok(earlier < toTimestamp(INSTANT))
})

test('an absent timestamp reads back as an absent instant', () => {
  assert.equal(fromNullableTimestamp(null), null)
  assert.deepEqual(fromNullableTimestamp(toTimestamp(INSTANT)), INSTANT)
})

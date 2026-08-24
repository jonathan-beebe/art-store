import { test } from 'node:test'
import assert from 'node:assert/strict'
import { decideRateLimit, windowStart } from './rate-limit-window.ts'

test('the window start floors to the window boundary at or before now', () => {
  const start = windowStart(new Date('2026-08-23T18:07:42.000Z'), 900)

  assert.equal(start.toISOString(), '2026-08-23T18:00:00.000Z')
})

test('now sitting exactly on a boundary starts its own window', () => {
  const start = windowStart(new Date('2026-08-23T18:00:00.000Z'), 900)

  assert.equal(start.toISOString(), '2026-08-23T18:00:00.000Z')
})

test('a count at or under the limit does not trip', () => {
  const now = new Date('2026-08-23T18:07:00.000Z')
  const start = windowStart(now, 900)

  assert.deepEqual(decideRateLimit(5, 5, start, 900, now), { tripped: false })
  assert.deepEqual(decideRateLimit(1, 5, start, 900, now), { tripped: false })
})

test('a count over the limit trips, with the seconds left in the window', () => {
  const now = new Date('2026-08-23T18:07:00.000Z')
  const start = windowStart(now, 900)

  // The window opened at 18:00 and is 900s (15m) long, so it ends at 18:15;
  // 18:07 is 480s short of that.
  assert.deepEqual(decideRateLimit(6, 5, start, 900, now), { tripped: true, retryAfterSeconds: 480 })
})

test('retryAfterSeconds is never less than one, even a millisecond before the window ends', () => {
  const start = new Date('2026-08-23T18:00:00.000Z')
  const now = new Date('2026-08-23T18:14:59.999Z')

  const decision = decideRateLimit(6, 5, start, 900, now)

  assert.deepEqual(decision, { tripped: true, retryAfterSeconds: 1 })
})

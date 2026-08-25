import { test } from 'node:test'
import assert from 'node:assert/strict'
import { expiredWindowCutoff } from './expired-window-cutoff.ts'
import type { RateLimit } from './rate-limit-value.ts'

test('the largest configured window decides the cutoff', () => {
  const asOf = new Date('2026-08-23T18:00:00.000Z')
  const limits: RateLimit[] = [
    { count: 5, windowSeconds: 900 },
    { count: 10, windowSeconds: 3600 },
    { count: 20, windowSeconds: 60 },
  ]

  const cutoff = expiredWindowCutoff(asOf, limits)

  assert.equal(cutoff?.toISOString(), '2026-08-23T17:00:00.000Z')
})

test('an off limit is ignored when a bigger window is present', () => {
  const asOf = new Date('2026-08-23T18:00:00.000Z')
  const limits: RateLimit[] = ['off', { count: 5, windowSeconds: 900 }]

  const cutoff = expiredWindowCutoff(asOf, limits)

  assert.equal(cutoff?.toISOString(), '2026-08-23T17:45:00.000Z')
})

test('every limit off is nothing safely prunable', () => {
  const asOf = new Date('2026-08-23T18:00:00.000Z')
  const limits: RateLimit[] = ['off', 'off']

  assert.equal(expiredWindowCutoff(asOf, limits), null)
})

test('no configured limits is nothing safely prunable', () => {
  const asOf = new Date('2026-08-23T18:00:00.000Z')

  assert.equal(expiredWindowCutoff(asOf, []), null)
})

test('the cutoff is asOf minus the largest window, exactly', () => {
  const asOf = new Date('2026-08-23T18:00:00.000Z')
  const limits: RateLimit[] = [{ count: 1, windowSeconds: 5400 }]

  const cutoff = expiredWindowCutoff(asOf, limits)

  assert.equal(cutoff?.toISOString(), '2026-08-23T16:30:00.000Z')
})

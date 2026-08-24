import { test } from 'node:test'
import assert from 'node:assert/strict'
import { SWEEPABLE_ORDER_STATUS, staleOrderCutoff } from './stale-order.ts'

const NOW = new Date('2026-08-23T12:00:00.000Z')

test('the sweep touches unverified orders only', () => {
  assert.equal(SWEEPABLE_ORDER_STATUS, 'pending_verification')
})

test('the default window reaches back a day', () => {
  assert.equal(staleOrderCutoff(NOW, 24).toISOString(), '2026-08-22T12:00:00.000Z')
})

test('a shorter window reaches back less far', () => {
  assert.equal(staleOrderCutoff(NOW, 1).toISOString(), '2026-08-23T11:00:00.000Z')
})

test('the cutoff moves with the clock rather than the calendar day', () => {
  assert.equal(
    staleOrderCutoff(new Date('2026-08-23T12:30:45.500Z'), 24).toISOString(),
    '2026-08-22T12:30:45.500Z',
  )
})

test('a window that is not a positive number of hours is a caller mistake', () => {
  assert.throws(() => staleOrderCutoff(NOW, 0), RangeError)
  assert.throws(() => staleOrderCutoff(NOW, -1), RangeError)
  assert.throws(() => staleOrderCutoff(NOW, Number.NaN), RangeError)
})

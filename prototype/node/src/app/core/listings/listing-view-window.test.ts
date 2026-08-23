import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isRecordedOncePerHour, viewWindowStart } from './listing-view-window.ts'

test('the start of the hour maps to itself', () => {
  const start = new Date('2026-08-22T14:00:00.000Z')
  assert.equal(viewWindowStart(start).toISOString(), '2026-08-22T14:00:00.000Z')
})

test('the last millisecond of the hour maps back to its start', () => {
  const end = new Date('2026-08-22T14:59:59.999Z')
  assert.equal(viewWindowStart(end).toISOString(), '2026-08-22T14:00:00.000Z')
})

test('a moment mid-hour maps to that hour start', () => {
  const mid = new Date('2026-08-22T14:32:07.123Z')
  assert.equal(viewWindowStart(mid).toISOString(), '2026-08-22T14:00:00.000Z')
})

test('an hour boundary crossing midnight rolls the date forward', () => {
  const justAfterMidnight = new Date('2026-08-23T00:00:00.001Z')
  assert.equal(viewWindowStart(justAfterMidnight).toISOString(), '2026-08-23T00:00:00.000Z')
})

test('a view is recorded once an hour', () => {
  assert.equal(isRecordedOncePerHour('view'), true)
})

test('every deliberate interaction is recorded every time', () => {
  assert.equal(isRecordedOncePerHour('favorite'), false)
  assert.equal(isRecordedOncePerHour('unfavorite'), false)
  assert.equal(isRecordedOncePerHour('cart_add'), false)
})

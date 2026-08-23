import { test } from 'node:test'
import assert from 'node:assert/strict'
import { dateLabel, dateTimeLabel, dayFromReportKey, dayLabel, timestampLabel } from './day-label.ts'

test('it reads an instant as the day it falls on', () => {
  assert.equal(dayLabel('2026-08-24T12:00:00.000Z'), '24 August 2026')
})

test('a late evening instant keeps its own date', () => {
  assert.equal(dayLabel('2026-08-24T23:59:59.999Z'), '24 August 2026')
})

test('dateLabel reads a timestamp as a calendar date', () => {
  assert.equal(dateLabel('2026-08-09T00:00:00.000Z'), 'Aug 9, 2026')
})

test('dateTimeLabel appends a 12-hour clock time', () => {
  assert.equal(dateTimeLabel('2026-08-09T15:04:00.000Z'), 'Aug 9, 2026 3:04pm')
})

test('dateTimeLabel reads midnight as 12am', () => {
  assert.equal(dateTimeLabel('2026-08-09T00:00:00.000Z'), 'Aug 9, 2026 12:00am')
})

test('dateTimeLabel reads noon as 12pm', () => {
  assert.equal(dateTimeLabel('2026-08-09T12:00:00.000Z'), 'Aug 9, 2026 12:00pm')
})

test('timestampLabel reads an instant to the minute, sortable as printed', () => {
  assert.equal(timestampLabel('2026-08-24T12:00:00.000Z'), '2026-08-24 12:00')
})

test('timestampLabel keeps a two-digit hour past noon in 24-hour time', () => {
  assert.equal(timestampLabel('2026-08-24T23:05:00.000Z'), '2026-08-24 23:05')
})

test('dayFromReportKey labels a report row for a table cell', () => {
  assert.equal(dayFromReportKey('2026-08-09'), 'Aug 9')
})

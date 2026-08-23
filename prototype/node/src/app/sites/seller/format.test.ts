import { test } from 'node:test'
import assert from 'node:assert/strict'
import { dollarsInputValue, formatDate, formatDateTime, formatDay } from './format.ts'

test('formatDay labels a report row for a table cell', () => {
  assert.equal(formatDay('2026-08-09'), 'Aug 9')
})

test('formatDate reads a timestamp as a calendar date', () => {
  assert.equal(formatDate('2026-08-09T00:00:00.000Z'), 'Aug 9, 2026')
})

test('formatDateTime appends a 12-hour clock time', () => {
  assert.equal(formatDateTime('2026-08-09T15:04:00.000Z'), 'Aug 9, 2026 3:04pm')
})

test('formatDateTime reads midnight as 12am', () => {
  assert.equal(formatDateTime('2026-08-09T00:00:00.000Z'), 'Aug 9, 2026 12:00am')
})

test('formatDateTime reads noon as 12pm', () => {
  assert.equal(formatDateTime('2026-08-09T12:00:00.000Z'), 'Aug 9, 2026 12:00pm')
})

test('dollarsInputValue writes cents as a plain decimal amount', () => {
  assert.equal(dollarsInputValue(45_000), '450.00')
})

test('dollarsInputValue pads a fractional amount under ten cents', () => {
  assert.equal(dollarsInputValue(105), '1.05')
})

test('dollarsInputValue writes zero as 0.00', () => {
  assert.equal(dollarsInputValue(0), '0.00')
})

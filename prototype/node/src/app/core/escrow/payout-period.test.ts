import { test } from 'node:test'
import assert from 'node:assert/strict'
import { payoutPeriodEndingBefore, payoutPeriodEndsAt, payoutPeriodLabel } from './payout-period.ts'

test('a Monday settles the week that just ended', () => {
  const period = payoutPeriodEndingBefore(new Date('2026-08-24T09:00:00Z'))

  assert.deepEqual(period, { firstDay: '2026-08-17', lastDay: '2026-08-23' })
})

test('every day of a week settles the same period', () => {
  const period = payoutPeriodEndingBefore(new Date('2026-08-28T23:00:00Z'))

  assert.deepEqual(period, { firstDay: '2026-08-17', lastDay: '2026-08-23' })
})

test('a Sunday still belongs to the week it is closing', () => {
  const period = payoutPeriodEndingBefore(new Date('2026-08-30T09:00:00Z'))

  assert.deepEqual(period, { firstDay: '2026-08-17', lastDay: '2026-08-23' })
})

test('the period ends with the last instant of its last day', () => {
  const period = payoutPeriodEndingBefore(new Date('2026-08-24T09:00:00Z'))

  assert.equal(payoutPeriodEndsAt(period).toISOString(), '2026-08-23T23:59:59.999Z')
})

test('it labels itself with both ends', () => {
  const period = payoutPeriodEndingBefore(new Date('2026-08-24T09:00:00Z'))

  assert.equal(payoutPeriodLabel(period), '2026-08-17 to 2026-08-23')
})

test('a period that crosses the turn of the year keeps each day in its own year', () => {
  const period = payoutPeriodEndingBefore(new Date('2027-01-04T09:00:00Z'))

  assert.deepEqual(period, { firstDay: '2026-12-28', lastDay: '2027-01-03' })
  assert.equal(payoutPeriodEndsAt(period).toISOString(), '2027-01-03T23:59:59.999Z')
})

test('asOf on New Year\'s Day still settles the week that closed in the old year', () => {
  const period = payoutPeriodEndingBefore(new Date('2027-01-01T00:00:00.000Z'))

  assert.deepEqual(period, { firstDay: '2026-12-21', lastDay: '2026-12-27' })
})

test('a period spanning a leap day includes it without shifting the week', () => {
  const period = payoutPeriodEndingBefore(new Date('2028-03-01T09:00:00Z'))

  assert.deepEqual(period, { firstDay: '2028-02-21', lastDay: '2028-02-27' })
})

test('asOf at the exact instant of Monday 00:00Z still settles the week before', () => {
  const period = payoutPeriodEndingBefore(new Date('2026-08-24T00:00:00.000Z'))

  assert.deepEqual(period, { firstDay: '2026-08-17', lastDay: '2026-08-23' })
})

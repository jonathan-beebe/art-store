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

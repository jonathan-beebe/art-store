import { test } from 'node:test'
import assert from 'node:assert/strict'
import { dayLabel } from './day-label.ts'

test('it reads an instant as the day it falls on', () => {
  assert.equal(dayLabel('2026-08-24T12:00:00.000Z'), '24 August 2026')
})

test('a late evening instant keeps its own date', () => {
  assert.equal(dayLabel('2026-08-24T23:59:59.999Z'), '24 August 2026')
})

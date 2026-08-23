import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixedClock, systemClock } from './clock.ts'

test('a fixed clock reports the same instant on every read', () => {
  const clock = fixedClock(new Date('2026-08-24T12:00:00.000Z'))

  assert.equal(clock.now().toISOString(), '2026-08-24T12:00:00.000Z')
  assert.equal(clock.now().toISOString(), '2026-08-24T12:00:00.000Z')
})

test('a fixed clock hands out a new Date each read', () => {
  const clock = fixedClock(new Date('2026-08-24T12:00:00.000Z'))

  const first = clock.now()
  first.setFullYear(1999)

  assert.equal(clock.now().getFullYear(), 2026)
})

test('the system clock reports the current time', () => {
  const before = Date.now()

  const reading = systemClock.now().getTime()

  assert.ok(reading >= before)
  assert.ok(reading <= Date.now())
})

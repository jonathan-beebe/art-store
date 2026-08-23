import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activityTimeline, activityWindow } from './activity-timeline.ts'

test('it returns one row per day, oldest first', () => {
  const days = activityTimeline({}, { endsOn: new Date('2026-08-22T17:30:00.000Z'), days: 3 })

  assert.deepEqual(
    days.map((day) => day.day),
    ['2026-08-20', '2026-08-21', '2026-08-22'],
  )
})

test('it reads the counts recorded for a day', () => {
  const counts = { '2026-08-21': { view: 4, favorite: 1, cart_add: 2 } }

  const days = activityTimeline(counts, { endsOn: new Date('2026-08-22T00:00:00.000Z'), days: 2 })

  assert.equal(days[0]?.totals.views, 4)
  assert.equal(days[0]?.totals.favorites, 1)
  assert.equal(days[0]?.totals.cartAdds, 2)
})

test('a day with no events counts zero', () => {
  const counts = { '2026-08-22': { view: 7 } }

  const days = activityTimeline(counts, { endsOn: new Date('2026-08-22T00:00:00.000Z'), days: 2 })

  assert.equal(days[0]?.totals.views, 0)
  assert.equal(days[1]?.totals.views, 7)
})

test('it ignores counts outside the window', () => {
  const counts = { '2026-07-01': { view: 99 } }

  const days = activityTimeline(counts, { endsOn: new Date('2026-08-22T00:00:00.000Z'), days: 2 })

  assert.equal(days.reduce((total, day) => total + day.totals.views, 0), 0)
})

test('a fortnight ends on the day asked for', () => {
  const days = activityTimeline({}, { endsOn: new Date('2026-08-22T00:00:00.000Z'), days: 14 })

  assert.equal(days[0]?.day, '2026-08-09')
  assert.equal(days.at(-1)?.day, '2026-08-22')
})

test('it refuses a window shorter than a day', () => {
  assert.throws(
    () => activityTimeline({}, { endsOn: new Date('2026-08-22T00:00:00.000Z'), days: 0 }),
    RangeError,
  )
})

test('a one-day window starts at midnight the same day', () => {
  const window = activityWindow(new Date('2026-08-22T17:30:00.000Z'), 1)

  assert.equal(window.since.toISOString(), '2026-08-22T00:00:00.000Z')
  assert.equal(window.days, 1)
})

test('a two-day window starts at midnight the earlier day', () => {
  const window = activityWindow(new Date('2026-08-22T17:30:00.000Z'), 2)

  assert.equal(window.since.toISOString(), '2026-08-21T00:00:00.000Z')
})

test("the window's since matches the timeline's first row", () => {
  const endsOn = new Date('2026-08-22T17:30:00.000Z')
  const window = activityWindow(endsOn, 14)
  const timeline = activityTimeline({}, { endsOn, days: 14 })

  assert.equal(window.since.toISOString().slice(0, 10), timeline[0]?.day)
})

test('activityWindow refuses a window shorter than a day', () => {
  assert.throws(() => activityWindow(new Date('2026-08-22T00:00:00.000Z'), 0), RangeError)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { pageViewWeek } from './page-view-window.ts'

test('the week is the seven days ending today', () => {
  assert.deepEqual(pageViewWeek('2026-08-24'), { firstDay: '2026-08-18', lastDay: '2026-08-24' })
})

test('the week reaches back over the end of a month', () => {
  assert.deepEqual(pageViewWeek('2026-09-02'), { firstDay: '2026-08-27', lastDay: '2026-09-02' })
})

test('the week reaches back over the end of a year', () => {
  assert.deepEqual(pageViewWeek('2027-01-03'), { firstDay: '2026-12-28', lastDay: '2027-01-03' })
})

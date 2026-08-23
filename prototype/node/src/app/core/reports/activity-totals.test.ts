import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activityTotals } from './activity-totals.ts'

test('it reads the count of each event the report shows', () => {
  const totals = activityTotals({ view: 12, favorite: 3, cart_add: 2 })

  assert.equal(totals.views, 12)
  assert.equal(totals.favorites, 3)
  assert.equal(totals.cartAdds, 2)
})

test('an event that has not happened counts zero', () => {
  const totals = activityTotals({ view: 12 })

  assert.equal(totals.favorites, 0)
  assert.equal(totals.cartAdds, 0)
})

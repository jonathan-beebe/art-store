import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import {
  planOrderPlacement,
  unavailableNotices,
  type PlaceableLine,
} from './order-placement.ts'

function line(overrides: Partial<PlaceableLine> = {}): PlaceableLine {
  return {
    listingId: fixtureId('lst', 1),
    title: 'Harbour at dusk',
    status: 'for_sale',
    availableQuantity: 1,
    quantity: 1,
    hasActiveRemoval: false,
    ...overrides,
  }
}

test('a cart of listings still for sale is placeable', () => {
  const lines = [line(), line({ listingId: fixtureId('lst', 2), title: 'Low tide' })]

  assert.deepEqual(planOrderPlacement(lines), { ok: true, lines })
})

test('an empty cart has nothing standing in the way', () => {
  assert.deepEqual(planOrderPlacement([]), { ok: true, lines: [] })
})

test('a listing an admin removed is unavailable', () => {
  const plan = planOrderPlacement([line({ hasActiveRemoval: true })])

  assert.deepEqual(plan, {
    ok: false,
    unavailable: [{ listingId: fixtureId('lst', 1), title: 'Harbour at dusk', reason: 'removed' }],
  })
})

test('a listing another buyer took is sold out', () => {
  const plan = planOrderPlacement([line({ status: 'sold', availableQuantity: 0 })])

  assert.deepEqual(plan, {
    ok: false,
    unavailable: [{ listingId: fixtureId('lst', 1), title: 'Harbour at dusk', reason: 'sold_out' }],
  })
})

test('a listing the seller archived is off sale', () => {
  const plan = planOrderPlacement([line({ status: 'archived' })])

  assert.deepEqual(plan, {
    ok: false,
    unavailable: [{ listingId: fixtureId('lst', 1), title: 'Harbour at dusk', reason: 'off_sale' }],
  })
})

test('a listing back to draft is off sale', () => {
  const plan = planOrderPlacement([line({ status: 'draft' })])

  assert.equal(plan.ok, false)
  assert.equal(plan.ok === false && plan.unavailable[0]?.reason, 'off_sale')
})

test('a cart asking for more than is left is short of stock', () => {
  const plan = planOrderPlacement([line({ availableQuantity: 1, quantity: 2 })])

  assert.deepEqual(plan, {
    ok: false,
    unavailable: [{ listingId: fixtureId('lst', 1), title: 'Harbour at dusk', reason: 'short_stock' }],
  })
})

test('nothing left to sell reads as sold out rather than short of stock', () => {
  const plan = planOrderPlacement([line({ availableQuantity: 0, quantity: 2 })])

  assert.equal(plan.ok === false && plan.unavailable[0]?.reason, 'sold_out')
})

test('a removal outranks whatever the listing status says', () => {
  const plan = planOrderPlacement([line({ status: 'sold', hasActiveRemoval: true })])

  assert.equal(plan.ok === false && plan.unavailable[0]?.reason, 'removed')
})

test('every line standing in the way is named, not just the first', () => {
  const plan = planOrderPlacement([
    line({ listingId: fixtureId('lst', 7), title: 'Low tide', status: 'sold' }),
    line({ listingId: fixtureId('lst', 8), title: 'Harbour at dusk' }),
    line({ listingId: fixtureId('lst', 9), title: 'Long shore', hasActiveRemoval: true }),
  ])

  assert.deepEqual(plan.ok === false ? plan.unavailable.map((entry) => entry.listingId) : [], [fixtureId('lst', 7), fixtureId('lst', 9)])
})

test('each reason reads as a sentence beside the piece it is about', () => {
  const notices = unavailableNotices([
    { listingId: fixtureId('lst', 1), title: 'Harbour at dusk', reason: 'removed' },
    { listingId: fixtureId('lst', 2), title: 'Low tide', reason: 'sold_out' },
    { listingId: fixtureId('lst', 3), title: 'Long shore', reason: 'off_sale' },
    { listingId: fixtureId('lst', 4), title: 'First light', reason: 'short_stock' },
  ])

  assert.deepEqual(notices, [
    { title: 'Harbour at dusk', notice: 'no longer available' },
    { title: 'Low tide', notice: 'sold out' },
    { title: 'Long shore', notice: 'no longer for sale' },
    { title: 'First light', notice: 'no longer in stock in that quantity' },
  ])
})

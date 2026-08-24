import { test } from 'node:test'
import assert from 'node:assert/strict'
import { TransitionError } from '../transition-error.ts'
import {
  FULFILLMENT_STATUSES,
  FULFILLMENT_STATUS_TRANSITIONS,
  canTransitionFulfillment,
  transitionFulfillment,
  hasDeparted,
  isReversed,
} from './fulfillment-status.ts'

test('FULFILLMENT_STATUSES names every status', () => {
  assert.deepEqual(FULFILLMENT_STATUSES, [
    'awaiting_shipment',
    'shipped',
    'delivered',
    'declined',
    'refunded',
  ])
})

test('a fulfillment awaiting shipment ships', () => {
  assert.equal(canTransitionFulfillment('awaiting_shipment', 'shipped'), true)
})

test('a shipped fulfillment is delivered', () => {
  assert.equal(canTransitionFulfillment('shipped', 'delivered'), true)
})

test('a fulfillment cannot skip shipping', () => {
  assert.equal(canTransitionFulfillment('awaiting_shipment', 'delivered'), false)
})

test('a delivered fulfillment goes nowhere but a refund', () => {
  assert.deepEqual(FULFILLMENT_STATUS_TRANSITIONS.delivered, ['refunded'])
})

test('a seller declines only before shipping', () => {
  assert.equal(canTransitionFulfillment('awaiting_shipment', 'declined'), true)
  assert.equal(canTransitionFulfillment('shipped', 'declined'), false)
  assert.equal(canTransitionFulfillment('delivered', 'declined'), false)
})

test('an admin refunds from every stage a live fulfillment reaches', () => {
  assert.equal(canTransitionFulfillment('awaiting_shipment', 'refunded'), true)
  assert.equal(canTransitionFulfillment('shipped', 'refunded'), true)
  assert.equal(canTransitionFulfillment('delivered', 'refunded'), true)
})

test('a declined fulfillment cannot ship, be declined again, or be refunded', () => {
  assert.deepEqual(FULFILLMENT_STATUS_TRANSITIONS.declined, [])
  assert.equal(canTransitionFulfillment('declined', 'shipped'), false)
  assert.equal(canTransitionFulfillment('declined', 'declined'), false)
  assert.equal(canTransitionFulfillment('declined', 'refunded'), false)
})

test('a refunded fulfillment cannot be refunded again', () => {
  assert.deepEqual(FULFILLMENT_STATUS_TRANSITIONS.refunded, [])
  assert.equal(canTransitionFulfillment('refunded', 'refunded'), false)
})

test('transition returns the next status', () => {
  assert.equal(transitionFulfillment('awaiting_shipment', 'shipped'), 'shipped')
})

test('transition refuses a second delivery', () => {
  assert.throws(
    () => transitionFulfillment('delivered', 'delivered'),
    (error: unknown) => error instanceof TransitionError && error.message === 'A fulfillment cannot move from delivered to delivered.',
  )
})

test('transition refuses a ship after a decline', () => {
  assert.throws(
    () => transitionFulfillment('declined', 'shipped'),
    (error: unknown) => error instanceof TransitionError && error.message === 'A fulfillment cannot move from declined to shipped.',
  )
})

test('a shipped or delivered fulfillment has departed', () => {
  assert.equal(hasDeparted('shipped'), true)
  assert.equal(hasDeparted('delivered'), true)
})

test('a fulfillment awaiting shipment has not departed', () => {
  assert.equal(hasDeparted('awaiting_shipment'), false)
})

test('a reversed fulfillment never departed', () => {
  assert.equal(hasDeparted('declined'), false)
  assert.equal(hasDeparted('refunded'), false)
})

test('declined and refunded are the reversed statuses', () => {
  assert.equal(isReversed('declined'), true)
  assert.equal(isReversed('refunded'), true)
  assert.equal(isReversed('awaiting_shipment'), false)
  assert.equal(isReversed('shipped'), false)
  assert.equal(isReversed('delivered'), false)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { TransitionError } from '../transition-error.ts'
import {
  FULFILLMENT_STATUSES,
  FULFILLMENT_STATUS_TRANSITIONS,
  canTransitionFulfillment,
  transitionFulfillment,
  hasDeparted,
} from './fulfillment-status.ts'

test('FULFILLMENT_STATUSES names every status', () => {
  assert.deepEqual(FULFILLMENT_STATUSES, ['awaiting_shipment', 'shipped', 'delivered'])
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

test('a delivered fulfillment goes nowhere', () => {
  assert.deepEqual(FULFILLMENT_STATUS_TRANSITIONS.delivered, [])
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

test('a shipped or delivered fulfillment has departed', () => {
  assert.equal(hasDeparted('shipped'), true)
  assert.equal(hasDeparted('delivered'), true)
})

test('a fulfillment awaiting shipment has not departed', () => {
  assert.equal(hasDeparted('awaiting_shipment'), false)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { BrokenContractError } from '../defect.ts'
import { refused } from '../refusal.ts'
import {
  FULFILLMENT_STATUSES,
  FULFILLMENT_STATUS_TRANSITIONS,
  canTransitionFulfillment,
  transitionFulfillment,
  fulfillmentMovedTo,
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
  assert.deepEqual(transitionFulfillment('awaiting_shipment', 'shipped'), { outcome: 'allowed', status: 'shipped' })
})

test('transition refuses a second delivery', () => {
  assert.deepEqual(
    transitionFulfillment('delivered', 'delivered'),
    refused('illegal_transition', { status_from: 'delivered', status_to: 'delivered' }),
  )
})

test('transition refuses a ship after a decline', () => {
  assert.deepEqual(
    transitionFulfillment('declined', 'shipped'),
    refused('illegal_transition', { status_from: 'declined', status_to: 'shipped' }),
  )
})

test('fulfillmentMovedTo returns the status for a legal move', () => {
  assert.equal(fulfillmentMovedTo('awaiting_shipment', 'shipped'), 'shipped')
})

test('fulfillmentMovedTo throws for a move the table does not allow', () => {
  assert.throws(
    () => fulfillmentMovedTo('delivered', 'delivered'),
    (error: unknown) =>
      error instanceof BrokenContractError &&
      error.reason === 'illegal_transition' &&
      error.message === 'A fulfillment cannot move from delivered to delivered.' &&
      JSON.stringify(error.data) === JSON.stringify({ status_from: 'delivered', status_to: 'delivered' }),
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

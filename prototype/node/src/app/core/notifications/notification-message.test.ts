import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { cents } from '../money.ts'
import {
  fulfillmentDeclinedMessage,
  itemSoldMessage,
  newMessageMessage,
  orderCancelledMessage,
  orderShippedMessage,
  refundIssuedMessage,
  signInLinkMessage,
} from './notification-message.ts'

const ORDER_ID = fixtureId('ord', 7)

test('a sale tells the seller what is held and why', () => {
  const message = itemSoldMessage(ORDER_ID, cents(40_500))

  assert.equal(message.subject, 'Item sold')
  assert.equal(message.body, `Order ${ORDER_ID} is paid. $405.00 is held until the customer confirms delivery.`)
  assert.equal(message.url, null)
})

test('a sale message takes a url to the page it is about', () => {
  const message = itemSoldMessage(ORDER_ID, cents(40_500), '/seller/orders/7')

  assert.equal(message.url, '/seller/orders/7')
  assert.equal(message.subject, 'Item sold')
})

test('a shipment tells the customer how to track it', () => {
  const message = orderShippedMessage(ORDER_ID, 'USPS', '9400111899')

  assert.equal(message.subject, 'Order shipped')
  assert.equal(message.body, `Order ${ORDER_ID} shipped with USPS. Tracking number 9400111899.`)
  assert.equal(message.url, null)
})

test('a shipment message takes a url to the page it is about', () => {
  const message = orderShippedMessage(ORDER_ID, 'USPS', '9400111899', '/orders/7')

  assert.equal(message.url, '/orders/7')
})

test('a new message points at the conversation it is about', () => {
  const message = newMessageMessage('Sunset over the bay')

  assert.equal(message.subject, 'New message')
  assert.equal(message.body, 'You have a new message about Sunset over the bay.')
  assert.equal(message.url, null)
})

test('a new message notice takes a url to the conversation', () => {
  const message = newMessageMessage('Sunset over the bay', '/messages/3')

  assert.equal(message.url, '/messages/3')
})

test('a sign-in message carries the link and says how long it lasts', () => {
  const message = signInLinkMessage('http://localhost:4000/auth/magic/abc')

  assert.equal(message.subject, 'Your Art Store sign-in link')
  assert.equal(message.body, 'Open the link below to sign in. It expires in 15 minutes and works once.')
  assert.equal(message.url, 'http://localhost:4000/auth/magic/abc')
})

test('a decline tells the customer why and what comes back', () => {
  const message = fulfillmentDeclinedMessage(ORDER_ID, cents(45_000), 'Damaged in the kiln')

  assert.equal(message.subject, 'Order declined')
  assert.equal(
    message.body,
    `The seller declined their part of order ${ORDER_ID}: Damaged in the kiln. $450.00 is refunded.`,
  )
  assert.equal(message.url, null)
})

test('a decline notice takes a url to the order it is about', () => {
  assert.equal(fulfillmentDeclinedMessage(ORDER_ID, cents(45_000), 'Damaged', '/orders/7').url, '/orders/7')
})

test('a refund names the platform as the one who issued it', () => {
  const message = refundIssuedMessage(ORDER_ID, cents(45_000), 'Never arrived')

  assert.equal(message.subject, 'Order refunded')
  assert.equal(message.body, `Art Store refunded $450.00 on order ${ORDER_ID}: Never arrived.`)
  assert.equal(message.url, null)
})

test('a refund notice takes a url to the order it is about', () => {
  assert.equal(refundIssuedMessage(ORDER_ID, cents(45_000), 'Never arrived', '/orders/7').url, '/orders/7')
})

test('a cancellation carries the reason it was cancelled for', () => {
  const message = orderCancelledMessage(ORDER_ID, 'Customer changed their mind')

  assert.equal(message.subject, 'Order cancelled')
  assert.equal(message.body, `Order ${ORDER_ID} was cancelled: Customer changed their mind.`)
  assert.equal(message.url, null)
})

test('a cancellation notice takes a url to the order it is about', () => {
  assert.equal(orderCancelledMessage(ORDER_ID, 'Stale', '/admin/orders/7').url, '/admin/orders/7')
})

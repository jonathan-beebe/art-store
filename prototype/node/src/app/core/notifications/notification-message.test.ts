import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  itemSoldMessage,
  orderShippedMessage,
  newMessageMessage,
  signInLinkMessage,
} from './notification-message.ts'

test('a sale tells the seller what is held and why', () => {
  const message = itemSoldMessage(7, 40_500)

  assert.equal(message.subject, 'Item sold')
  assert.equal(message.body, 'Order #7 is paid. $405.00 is held until the customer confirms delivery.')
  assert.equal(message.url, null)
})

test('a sale message takes a url to the page it is about', () => {
  const message = itemSoldMessage(7, 40_500, '/seller/orders/7')

  assert.equal(message.url, '/seller/orders/7')
  assert.equal(message.subject, 'Item sold')
})

test('a shipment tells the customer how to track it', () => {
  const message = orderShippedMessage(7, 'USPS', '9400111899')

  assert.equal(message.subject, 'Order shipped')
  assert.equal(message.body, 'Order #7 shipped with USPS. Tracking number 9400111899.')
  assert.equal(message.url, null)
})

test('a shipment message takes a url to the page it is about', () => {
  const message = orderShippedMessage(7, 'USPS', '9400111899', '/orders/7')

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

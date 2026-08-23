import { test } from 'node:test'
import assert from 'node:assert/strict'
import { conversationTopic } from './conversation-topic.ts'

test('both admin kinds are about the support desk rather than a subject row', () => {
  assert.equal(conversationTopic('admin_seller'), 'Art Store support')
  assert.equal(conversationTopic('admin_customer'), 'Art Store support')
})

test('a fulfillment thread is about the order it splits', () => {
  assert.equal(conversationTopic('fulfillment', { orderId: 12 }), 'order #12')
})

test('a listing question is about the piece, quoted', () => {
  assert.equal(conversationTopic('listing_question', { listingTitle: 'Nine Herons' }), '“Nine Herons”')
})

test('a subject row nobody named still reads as something', () => {
  assert.equal(conversationTopic('fulfillment', { orderId: null }), 'an order')
  assert.equal(conversationTopic('listing_question', {}), 'a listing')
})

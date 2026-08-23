import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  conversationSubject,
  isSameConversationSubject,
  type ConversationSubject,
} from './conversation-subject.ts'

test('an admin_seller opening fills the missing columns with null', () => {
  const subject = conversationSubject({ kind: 'admin_seller', adminId: 1, sellerId: 2 })

  assert.deepEqual(subject, {
    kind: 'admin_seller',
    sellerId: 2,
    customerId: null,
    adminId: 1,
    listingId: null,
    fulfillmentId: null,
  })
})

test('an admin_customer opening fills the missing columns with null', () => {
  const subject = conversationSubject({ kind: 'admin_customer', adminId: 1, customerId: 3 })

  assert.deepEqual(subject, {
    kind: 'admin_customer',
    sellerId: null,
    customerId: 3,
    adminId: 1,
    listingId: null,
    fulfillmentId: null,
  })
})

test('a fulfillment opening keeps both participants and the fulfillment column', () => {
  const subject = conversationSubject({ kind: 'fulfillment', sellerId: 1, customerId: 2, fulfillmentId: 9 })

  assert.deepEqual(subject, {
    kind: 'fulfillment',
    sellerId: 1,
    customerId: 2,
    adminId: null,
    listingId: null,
    fulfillmentId: 9,
  })
})

test('a listing_question opening keeps both participants and the listing column', () => {
  const subject = conversationSubject({ kind: 'listing_question', sellerId: 1, customerId: 2, listingId: 7 })

  assert.deepEqual(subject, {
    kind: 'listing_question',
    sellerId: 1,
    customerId: 2,
    adminId: null,
    listingId: 7,
    fulfillmentId: null,
  })
})

function subject(overrides: Partial<ConversationSubject> = {}): ConversationSubject {
  return {
    kind: 'listing_question',
    sellerId: null,
    customerId: null,
    adminId: null,
    listingId: null,
    fulfillmentId: null,
    ...overrides,
  }
}

test('same kind and same columns is the same subject', () => {
  const a = subject({ sellerId: 1, customerId: 2, listingId: 3 })
  const b = subject({ sellerId: 1, customerId: 2, listingId: 3 })
  assert.equal(isSameConversationSubject(a, b), true)
})

test('a different kind is a different subject', () => {
  const a = subject({ kind: 'listing_question' })
  const b = subject({ kind: 'fulfillment' })
  assert.equal(isSameConversationSubject(a, b), false)
})

test('a different id in any column is a different subject', () => {
  const a = subject({ sellerId: 1, customerId: 2, listingId: 3 })
  assert.equal(isSameConversationSubject(a, subject({ sellerId: 9, customerId: 2, listingId: 3 })), false)
  assert.equal(isSameConversationSubject(a, subject({ sellerId: 1, customerId: 9, listingId: 3 })), false)
  assert.equal(isSameConversationSubject(a, subject({ sellerId: 1, customerId: 2, listingId: 9 })), false)
  assert.equal(
    isSameConversationSubject(subject({ kind: 'admin_seller', adminId: 1 }), subject({ kind: 'admin_seller', adminId: 9 })),
    false,
  )
  assert.equal(
    isSameConversationSubject(subject({ kind: 'fulfillment', fulfillmentId: 1 }), subject({ kind: 'fulfillment', fulfillmentId: 9 })),
    false,
  )
})

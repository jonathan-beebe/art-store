import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  conversationSubject,
  isSameConversationSubject,
  missingConversationParts,
  type ConversationSubject,
} from './conversation-subject.ts'

test('an opening with nothing but a kind fills every other column with null', () => {
  const subject = conversationSubject({ kind: 'admin_seller' })

  assert.deepEqual(subject, {
    kind: 'admin_seller',
    sellerId: null,
    customerId: null,
    adminId: null,
    listingId: null,
    fulfillmentId: null,
  })
})

test('an opening keeps the columns the caller provided', () => {
  const subject = conversationSubject({ kind: 'listing_question', sellerId: 1, customerId: 2, listingId: 3 })

  assert.equal(subject.sellerId, 1)
  assert.equal(subject.customerId, 2)
  assert.equal(subject.listingId, 3)
  assert.equal(subject.adminId, null)
  assert.equal(subject.fulfillmentId, null)
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

test('a complete listing_question subject is missing nothing', () => {
  const parts = missingConversationParts(subject({ sellerId: 1, customerId: 2, listingId: 3 }))
  assert.deepEqual(parts, [])
})

test('missing parts for listing_question list participants then the subject column', () => {
  const parts = missingConversationParts(subject())
  assert.deepEqual(parts, ['sellerId', 'customerId', 'listingId'])
})

test('missing parts for fulfillment name the fulfillment column', () => {
  const parts = missingConversationParts(subject({ kind: 'fulfillment' }))
  assert.deepEqual(parts, ['sellerId', 'customerId', 'fulfillmentId'])
})

test('one filled participant still leaves the other and the subject missing', () => {
  const parts = missingConversationParts(subject({ sellerId: 1 }))
  assert.deepEqual(parts, ['customerId', 'listingId'])
})

test('the two admin kinds need no subject column', () => {
  const complete = missingConversationParts(subject({ kind: 'admin_seller', adminId: 1, sellerId: 2 }))
  assert.deepEqual(complete, [])

  const incomplete = missingConversationParts(subject({ kind: 'admin_seller' }))
  assert.deepEqual(incomplete, ['adminId', 'sellerId'])
})

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

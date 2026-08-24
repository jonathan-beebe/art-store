import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import {
  conversationSubject,
  isSameConversationSubject,
  type ConversationSubject,
} from './conversation-subject.ts'

test('an admin_seller opening fills the missing columns with null', () => {
  const subject = conversationSubject({ kind: 'admin_seller', adminId: fixtureId('adm', 1), sellerId: fixtureId('sel', 2) })

  assert.deepEqual(subject, {
    kind: 'admin_seller',
    sellerId: fixtureId('sel', 2),
    customerId: null,
    adminId: fixtureId('adm', 1),
    listingId: null,
    fulfillmentId: null,
  })
})

test('an admin_customer opening fills the missing columns with null', () => {
  const subject = conversationSubject({ kind: 'admin_customer', adminId: fixtureId('adm', 1), customerId: fixtureId('cus', 3) })

  assert.deepEqual(subject, {
    kind: 'admin_customer',
    sellerId: null,
    customerId: fixtureId('cus', 3),
    adminId: fixtureId('adm', 1),
    listingId: null,
    fulfillmentId: null,
  })
})

test('a fulfillment opening keeps both participants and the fulfillment column', () => {
  const subject = conversationSubject({ kind: 'fulfillment', sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 2), fulfillmentId: fixtureId('ful', 9) })

  assert.deepEqual(subject, {
    kind: 'fulfillment',
    sellerId: fixtureId('sel', 1),
    customerId: fixtureId('cus', 2),
    adminId: null,
    listingId: null,
    fulfillmentId: fixtureId('ful', 9),
  })
})

test('a listing_question opening keeps both participants and the listing column', () => {
  const subject = conversationSubject({ kind: 'listing_question', sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 2), listingId: fixtureId('lst', 7) })

  assert.deepEqual(subject, {
    kind: 'listing_question',
    sellerId: fixtureId('sel', 1),
    customerId: fixtureId('cus', 2),
    adminId: null,
    listingId: fixtureId('lst', 7),
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
  const a = subject({ sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 2), listingId: fixtureId('lst', 3) })
  const b = subject({ sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 2), listingId: fixtureId('lst', 3) })
  assert.equal(isSameConversationSubject(a, b), true)
})

test('a different kind is a different subject', () => {
  const a = subject({ kind: 'listing_question' })
  const b = subject({ kind: 'fulfillment' })
  assert.equal(isSameConversationSubject(a, b), false)
})

test('a different id in any column is a different subject', () => {
  const a = subject({ sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 2), listingId: fixtureId('lst', 3) })
  assert.equal(isSameConversationSubject(a, subject({ sellerId: fixtureId('sel', 9), customerId: fixtureId('cus', 2), listingId: fixtureId('lst', 3) })), false)
  assert.equal(isSameConversationSubject(a, subject({ sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 9), listingId: fixtureId('lst', 3) })), false)
  assert.equal(isSameConversationSubject(a, subject({ sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 2), listingId: fixtureId('lst', 9) })), false)
  assert.equal(
    isSameConversationSubject(subject({ kind: 'admin_seller', adminId: fixtureId('adm', 1) }), subject({ kind: 'admin_seller', adminId: fixtureId('adm', 9) })),
    false,
  )
  assert.equal(
    isSameConversationSubject(subject({ kind: 'fulfillment', fulfillmentId: fixtureId('ful', 1) }), subject({ kind: 'fulfillment', fulfillmentId: fixtureId('ful', 9) })),
    false,
  )
})

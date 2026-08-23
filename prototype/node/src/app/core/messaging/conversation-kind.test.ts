import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  admitsActor,
  isConversationKind,
  participantColumn,
  participantColumnsOf,
  subjectColumnOf,
} from './conversation-kind.ts'

test('every known kind is recognized', () => {
  assert.equal(isConversationKind('admin_seller'), true)
  assert.equal(isConversationKind('admin_customer'), true)
  assert.equal(isConversationKind('fulfillment'), true)
  assert.equal(isConversationKind('listing_question'), true)
})

test('a stranger is not a conversation kind', () => {
  assert.equal(isConversationKind('support_ticket'), false)
})

test('a non-string is not a conversation kind', () => {
  assert.equal(isConversationKind(42), false)
  assert.equal(isConversationKind(null), false)
  assert.equal(isConversationKind(undefined), false)
})

test('each actor type has its own participant column', () => {
  assert.equal(participantColumn('seller'), 'sellerId')
  assert.equal(participantColumn('customer'), 'customerId')
  assert.equal(participantColumn('admin'), 'adminId')
})

test('participant columns for admin_seller are admin and seller', () => {
  assert.deepEqual(participantColumnsOf('admin_seller'), ['adminId', 'sellerId'])
})

test('participant columns for admin_customer are admin and customer', () => {
  assert.deepEqual(participantColumnsOf('admin_customer'), ['adminId', 'customerId'])
})

test('participant columns for fulfillment are seller and customer', () => {
  assert.deepEqual(participantColumnsOf('fulfillment'), ['sellerId', 'customerId'])
})

test('participant columns for listing_question are seller and customer', () => {
  assert.deepEqual(participantColumnsOf('listing_question'), ['sellerId', 'customerId'])
})

test('the two admin kinds have no subject column', () => {
  assert.equal(subjectColumnOf('admin_seller'), null)
  assert.equal(subjectColumnOf('admin_customer'), null)
})

test('a fulfillment thread is about a fulfillment', () => {
  assert.equal(subjectColumnOf('fulfillment'), 'fulfillmentId')
})

test('a listing question thread is about a listing', () => {
  assert.equal(subjectColumnOf('listing_question'), 'listingId')
})

test('an admin is not a participant in a fulfillment thread', () => {
  assert.equal(admitsActor('fulfillment', 'admin'), false)
})

test('a seller is a participant in a fulfillment thread', () => {
  assert.equal(admitsActor('fulfillment', 'seller'), true)
})

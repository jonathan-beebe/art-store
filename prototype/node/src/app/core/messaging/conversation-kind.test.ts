import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  participantColumn,
  participantColumnsOf,
  subjectColumnOf,
} from './conversation-kind.ts'

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

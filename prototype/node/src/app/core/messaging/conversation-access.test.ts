import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  conversationAccess,
  isConversationParticipant,
  otherParticipants,
  type ConversationParticipants,
} from './conversation-access.ts'

function participants(overrides: Partial<ConversationParticipants> = {}): ConversationParticipants {
  return { sellerId: 1, customerId: 2, adminId: null, ...overrides }
}

test('the named seller is a participant', () => {
  assert.equal(isConversationParticipant(participants(), { type: 'seller', id: 1 }), true)
})

test('a different seller is not a participant', () => {
  assert.equal(isConversationParticipant(participants(), { type: 'seller', id: 9 }), false)
})

test('an actor whose column is null is never a participant', () => {
  assert.equal(isConversationParticipant(participants(), { type: 'admin', id: 1 }), false)
})

test('a participant may read', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: 2 })
  assert.equal(access.mayRead, true)
})

test('a non-participant may not read', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: 9 })
  assert.equal(access.mayRead, false)
  assert.equal(access.mayPost, false)
})

test('a participant with no standing flag may post', () => {
  const access = conversationAccess(participants(), { type: 'seller', id: 1 })
  assert.equal(access.mayPost, true)
})

test('a blocked participant may read but not post', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: 2, isBlocked: true })
  assert.equal(access.mayRead, true)
  assert.equal(access.mayPost, false)
})

test('a participant explicitly not blocked may post', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: 2, isBlocked: false })
  assert.equal(access.mayPost, true)
})

test('otherParticipants returns the other side of a two-party thread', () => {
  const others = otherParticipants(participants(), { type: 'seller', id: 1 })
  assert.deepEqual(others, [{ type: 'customer', id: 2 }])
})

test('otherParticipants excludes null columns', () => {
  const others = otherParticipants(participants({ adminId: 5 }), { type: 'seller', id: 1 })
  assert.deepEqual(others, [
    { type: 'customer', id: 2 },
    { type: 'admin', id: 5 },
  ])
})

test('otherParticipants orders seller, customer, admin', () => {
  const others = otherParticipants(participants({ adminId: 5 }), { type: 'customer', id: 2 })
  assert.deepEqual(others, [
    { type: 'seller', id: 1 },
    { type: 'admin', id: 5 },
  ])
})

test('otherParticipants excludes the sender by type and id, not type alone', () => {
  const others = otherParticipants(participants({ sellerId: 1 }), { type: 'seller', id: 9 })
  assert.deepEqual(others, [
    { type: 'seller', id: 1 },
    { type: 'customer', id: 2 },
  ])
})

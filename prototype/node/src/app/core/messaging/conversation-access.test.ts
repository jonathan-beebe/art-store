import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import {
  conversationAccess,
  isConversationParticipant,
  otherParticipants,
  type ConversationParticipants,
} from './conversation-access.ts'

function participants(overrides: Partial<ConversationParticipants> = {}): ConversationParticipants {
  return { sellerId: fixtureId('sel', 1), customerId: fixtureId('cus', 2), adminId: null, ...overrides }
}

test('the named seller is a participant', () => {
  assert.equal(isConversationParticipant(participants(), { type: 'seller', id: fixtureId('sel', 1) }), true)
})

test('a different seller is not a participant', () => {
  assert.equal(isConversationParticipant(participants(), { type: 'seller', id: fixtureId('sel', 9) }), false)
})

test('an actor whose column is null is never a participant', () => {
  assert.equal(isConversationParticipant(participants(), { type: 'admin', id: fixtureId('adm', 1) }), false)
})

test('a participant may read', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: fixtureId('cus', 2), isBlocked: false })
  assert.equal(access.mayRead, true)
})

test('a non-participant may not read', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: fixtureId('cus', 9), isBlocked: false })
  assert.equal(access.mayRead, false)
  assert.equal(access.mayPost, false)
})

test('a participant with no standing flag may post', () => {
  const access = conversationAccess(participants(), { type: 'seller', id: fixtureId('sel', 1) })
  assert.equal(access.mayPost, true)
})

test('a blocked participant may read but not post', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: fixtureId('cus', 2), isBlocked: true })
  assert.equal(access.mayRead, true)
  assert.equal(access.mayPost, false)
})

test('a participant explicitly not blocked may post', () => {
  const access = conversationAccess(participants(), { type: 'customer', id: fixtureId('cus', 2), isBlocked: false })
  assert.equal(access.mayPost, true)
})

test('otherParticipants returns the other side of a two-party thread', () => {
  const others = otherParticipants(participants(), { type: 'seller', id: fixtureId('sel', 1) })
  assert.deepEqual(others, [{ type: 'customer', id: fixtureId('cus', 2) }])
})

test('otherParticipants excludes null columns', () => {
  const others = otherParticipants(participants({ adminId: fixtureId('adm', 5) }), { type: 'seller', id: fixtureId('sel', 1) })
  assert.deepEqual(others, [
    { type: 'customer', id: fixtureId('cus', 2) },
    { type: 'admin', id: fixtureId('adm', 5) },
  ])
})

test('otherParticipants orders seller, customer, admin', () => {
  const others = otherParticipants(participants({ adminId: fixtureId('adm', 5) }), { type: 'customer', id: fixtureId('cus', 2) })
  assert.deepEqual(others, [
    { type: 'seller', id: fixtureId('sel', 1) },
    { type: 'admin', id: fixtureId('adm', 5) },
  ])
})

test('otherParticipants excludes the sender by type and id, not type alone', () => {
  const others = otherParticipants(participants({ sellerId: fixtureId('sel', 1) }), { type: 'seller', id: fixtureId('sel', 9) })
  assert.deepEqual(others, [
    { type: 'seller', id: fixtureId('sel', 1) },
    { type: 'customer', id: fixtureId('cus', 2) },
  ])
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { ConversationParticipants } from './conversation-access.ts'
import { ABSENT_COUNTERPART, counterpartName, customerName, senderName, type ParticipantNames } from './participant-name.ts'

function names(overrides: Partial<ParticipantNames> = {}): ParticipantNames {
  return { seller: new Map(), customer: new Map(), admin: new Map(), ...overrides }
}

function participants(overrides: Partial<ConversationParticipants> = {}): ConversationParticipants {
  return { sellerId: 1, customerId: 2, adminId: null, ...overrides }
}

test('counterpartName looks up the other side by type and id', () => {
  const known = names({ seller: new Map([[1, 'Blue Kiln Studio']]) })

  assert.equal(counterpartName(participants(), { type: 'customer', id: 2 }, known), 'Blue Kiln Studio')
})

test('counterpartName falls back when the thread has nobody left on the other side', () => {
  const known = names({ seller: new Map([[1, 'Blue Kiln Studio']]) })

  assert.equal(counterpartName(participants({ customerId: null }), { type: 'seller', id: 1 }, known), ABSENT_COUNTERPART)
})

test('counterpartName falls back when the other side has no entry in the map', () => {
  assert.equal(counterpartName(participants(), { type: 'customer', id: 2 }, names()), ABSENT_COUNTERPART)
})

test("senderName looks up a message's sender by type and id", () => {
  const known = names({ customer: new Map([[2, 'ada@example.test']]) })

  assert.equal(senderName({ senderType: 'customer', senderId: 2 }, known), 'ada@example.test')
})

test('senderName falls back when the sender has no entry in the map', () => {
  assert.equal(senderName({ senderType: 'admin', senderId: 9 }, names()), ABSENT_COUNTERPART)
})

test('a named customer reads by their name', () => {
  assert.equal(customerName({ id: 4, name: 'Casey Whitfield', email: 'casey@example.test' }), 'Casey Whitfield')
})

test('a verified customer with no name reads by their address', () => {
  assert.equal(customerName({ id: 4, name: null, email: 'casey@example.test' }), 'casey@example.test')
})

test('an anonymous customer reads by their row', () => {
  assert.equal(customerName({ id: 4, name: null, email: null }), 'Guest #4')
})

test('blank name and address fall through to the row', () => {
  assert.equal(customerName({ id: 7, name: '  ', email: '  ' }), 'Guest #7')
})

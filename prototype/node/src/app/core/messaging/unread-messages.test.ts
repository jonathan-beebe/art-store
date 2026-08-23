import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  isSentBy,
  isUnreadBy,
  totalUnreadMessages,
  unreadCountsByConversation,
  type ReadMarker,
} from './unread-messages.ts'

function marker(overrides: Partial<ReadMarker> = {}): ReadMarker {
  return { conversationId: 1, senderType: 'seller', senderId: 1, readAt: null, ...overrides }
}

test('isSentBy is true for the actor named as the sender', () => {
  assert.equal(isSentBy(marker(), { type: 'seller', id: 1 }), true)
})

test('isSentBy is false for a different actor of the same type', () => {
  assert.equal(isSentBy(marker(), { type: 'seller', id: 9 }), false)
})

test('isSentBy is false for an actor of a different type', () => {
  assert.equal(isSentBy(marker(), { type: 'customer', id: 1 }), false)
})

test('an unread message from the other side is unread by the reader', () => {
  assert.equal(isUnreadBy(marker(), { type: 'customer', id: 2 }), true)
})

test('a read message is not unread by anyone', () => {
  assert.equal(isUnreadBy(marker({ readAt: '2026-08-22T00:00:00.000Z' }), { type: 'customer', id: 2 }), false)
})

test('a message is never unread by its own sender', () => {
  assert.equal(isUnreadBy(marker(), { type: 'seller', id: 1 }), false)
})

test('an actor of the same type as the sender but a different id still sees it as unread', () => {
  assert.equal(isUnreadBy(marker(), { type: 'seller', id: 9 }), true)
})

test('unread counts fold only the unread messages, per conversation', () => {
  const messages = [
    marker({ conversationId: 1, senderType: 'seller', senderId: 1, readAt: null }),
    marker({ conversationId: 1, senderType: 'seller', senderId: 1, readAt: null }),
    marker({ conversationId: 2, senderType: 'seller', senderId: 1, readAt: null }),
  ]

  const counts = unreadCountsByConversation(messages, { type: 'customer', id: 2 })
  assert.equal(counts.get(1), 2)
  assert.equal(counts.get(2), 1)
})

test('a conversation with nothing unread does not appear in the map', () => {
  const messages = [
    marker({ conversationId: 1, senderType: 'customer', senderId: 2, readAt: null }),
    marker({ conversationId: 2, senderType: 'seller', senderId: 1, readAt: '2026-08-22T00:00:00.000Z' }),
  ]

  const counts = unreadCountsByConversation(messages, { type: 'customer', id: 2 })
  assert.equal(counts.has(1), false)
  assert.equal(counts.has(2), false)
})

test('the total sums every conversation in the map', () => {
  const counts = new Map([
    [1, 2],
    [2, 5],
  ])
  assert.equal(totalUnreadMessages(counts), 7)
})

test('the total over an empty map is zero', () => {
  assert.equal(totalUnreadMessages(new Map()), 0)
})

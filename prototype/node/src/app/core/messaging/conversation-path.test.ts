import { test } from 'node:test'
import assert from 'node:assert/strict'
import { conversationPath, inboxPath } from './conversation-path.ts'

test('a seller reads a conversation under the seller portal', () => {
  assert.equal(conversationPath('seller', 42), '/seller/messages/42')
})

test('a customer reads a conversation on the storefront', () => {
  assert.equal(conversationPath('customer', 42), '/messages/42')
})

test('an admin reads a conversation under the admin site', () => {
  assert.equal(conversationPath('admin', 42), '/admin/messages/42')
})

test('a seller inbox lives under the seller portal', () => {
  assert.equal(inboxPath('seller'), '/seller/messages')
})

test('a customer inbox lives on the storefront', () => {
  assert.equal(inboxPath('customer'), '/messages')
})

test('an admin inbox lives under the admin site', () => {
  assert.equal(inboxPath('admin'), '/admin/messages')
})

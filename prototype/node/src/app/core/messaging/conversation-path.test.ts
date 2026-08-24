import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { conversationPath } from './conversation-path.ts'

const CONVERSATION_ID = fixtureId('cnv', 42)

test('a seller reads a conversation under the seller portal', () => {
  assert.equal(conversationPath('seller', CONVERSATION_ID), `/seller/messages/${CONVERSATION_ID}`)
})

test('a customer reads a conversation on the storefront', () => {
  assert.equal(conversationPath('customer', CONVERSATION_ID), `/messages/${CONVERSATION_ID}`)
})

test('an admin reads a conversation under the admin site', () => {
  assert.equal(conversationPath('admin', CONVERSATION_ID), `/admin/messages/${CONVERSATION_ID}`)
})

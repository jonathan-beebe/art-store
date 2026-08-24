import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { planConversationFold, type ConversationFoldRow } from './conversation-fold-plan.ts'

const ANONYMOUS = fixtureId('cus', 1)
const VERIFIED = fixtureId('cus', 2)

function listingQuestion(overrides: Partial<ConversationFoldRow> = {}): ConversationFoldRow {
  return {
    id: fixtureId('cnv', 1),
    kind: 'listing_question',
    sellerId: fixtureId('sel', 1),
    customerId: ANONYMOUS,
    adminId: null,
    listingId: fixtureId('lst', 1),
    fulfillmentId: null,
    ...overrides,
  }
}

test('moves the conversation in place when the verified customer holds no thread on the subject', () => {
  const moving = listingQuestion()

  const plan = planConversationFold(moving, VERIFIED, [])

  assert.deepEqual(plan, { outcome: 'move', conversationId: moving.id })
})

test('moves in place when the verified customer holds threads on other subjects', () => {
  const moving = listingQuestion({ id: fixtureId('cnv', 1) })
  const other = listingQuestion({
    id: fixtureId('cnv', 2),
    customerId: VERIFIED,
    listingId: fixtureId('lst', 9),
  })

  const plan = planConversationFold(moving, VERIFIED, [other])

  assert.deepEqual(plan, { outcome: 'move', conversationId: moving.id })
})

test('absorbs onto the verified customer’s existing thread on the same subject', () => {
  const moving = listingQuestion({ id: fixtureId('cnv', 1) })
  const standing = listingQuestion({ id: fixtureId('cnv', 2), customerId: VERIFIED })

  const plan = planConversationFold(moving, VERIFIED, [standing])

  assert.deepEqual(plan, { outcome: 'absorb', movingId: moving.id, standingId: standing.id })
})

test('a different kind on the same participants does not absorb', () => {
  const moving = listingQuestion({ id: fixtureId('cnv', 1) })
  const differentKind = listingQuestion({
    id: fixtureId('cnv', 2),
    customerId: VERIFIED,
    kind: 'fulfillment',
    listingId: null,
    fulfillmentId: fixtureId('ful', 1),
  })

  const plan = planConversationFold(moving, VERIFIED, [differentKind])

  assert.deepEqual(plan, { outcome: 'move', conversationId: moving.id })
})

test('an admin_customer conversation with no listing or fulfillment still folds correctly', () => {
  const moving: ConversationFoldRow = {
    id: fixtureId('cnv', 1),
    kind: 'admin_customer',
    sellerId: null,
    customerId: ANONYMOUS,
    adminId: fixtureId('adm', 1),
    listingId: null,
    fulfillmentId: null,
  }
  const standing: ConversationFoldRow = { ...moving, id: fixtureId('cnv', 2), customerId: VERIFIED }

  const plan = planConversationFold(moving, VERIFIED, [standing])

  assert.deepEqual(plan, { outcome: 'absorb', movingId: moving.id, standingId: standing.id })
})

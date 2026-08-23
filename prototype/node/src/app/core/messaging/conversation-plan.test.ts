import { test } from 'node:test'
import assert from 'node:assert/strict'
import { planConversation } from './conversation-plan.ts'
import { type ConversationSubject } from './conversation-subject.ts'

type Row = ConversationSubject & { id: number }

function subject(overrides: Partial<ConversationSubject> = {}): ConversationSubject {
  return {
    kind: 'listing_question',
    sellerId: 1,
    customerId: 2,
    adminId: null,
    listingId: 3,
    fulfillmentId: null,
    ...overrides,
  }
}

function row(id: number, overrides: Partial<ConversationSubject> = {}): Row {
  return { id, ...subject(overrides) }
}

test('opens a new conversation when no existing row matches', () => {
  const plan = planConversation([], subject())
  assert.deepEqual(plan, { outcome: 'open', subject: subject() })
})

test('opens a new conversation when existing rows are on a different subject', () => {
  const existing = [row(1, { listingId: 999 })]
  const plan = planConversation(existing, subject())
  assert.deepEqual(plan, { outcome: 'open', subject: subject() })
})

test('reuses the row whose subject matches', () => {
  const match = row(1)
  const plan = planConversation([match], subject())
  assert.deepEqual(plan, { outcome: 'reuse', conversation: match })
})

test('reuses the first matching row when more than one matches', () => {
  const first = row(1)
  const second = row(2)
  const plan = planConversation([first, second], subject())
  assert.deepEqual(plan, { outcome: 'reuse', conversation: first })
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { faqPrefill } from './faq-prefill.ts'

test('an empty thread prefills nothing', () => {
  assert.deepEqual(faqPrefill([]), { question: '', answer: '', sourceMessageId: null })
})

test('the opening message is the question, the last seller reply is the answer', () => {
  const prefill = faqPrefill([
    { id: fixtureId('msg', 1), body: 'Is this framed?', isMine: false },
    { id: fixtureId('msg', 2), body: 'Not yet, but it can be.', isMine: true },
  ])

  assert.deepEqual(prefill, { question: 'Is this framed?', answer: 'Not yet, but it can be.', sourceMessageId: fixtureId('msg', 2) })
})

test('a thread with no seller reply prefills an empty answer', () => {
  const prefill = faqPrefill([{ id: fixtureId('msg', 1), body: 'Is this framed?', isMine: false }])

  assert.deepEqual(prefill, { question: 'Is this framed?', answer: '', sourceMessageId: null })
})

test('the answer is the seller’s most recent reply, not their first', () => {
  const prefill = faqPrefill([
    { id: fixtureId('msg', 1), body: 'Is this framed?', isMine: false },
    { id: fixtureId('msg', 2), body: 'Let me check.', isMine: true },
    { id: fixtureId('msg', 3), body: 'And a follow-up?', isMine: false },
    { id: fixtureId('msg', 4), body: 'Yes, framed in oak.', isMine: true },
  ])

  assert.equal(prefill.answer, 'Yes, framed in oak.')
  assert.equal(prefill.sourceMessageId, fixtureId('msg', 4))
})

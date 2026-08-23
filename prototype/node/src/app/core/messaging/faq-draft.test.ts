import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  FAQ_ANSWER_MAX_LENGTH,
  FAQ_QUESTION_MAX_LENGTH,
  faqDraftErrors,
  parseFaqDraft,
  type FaqDraftFields,
} from './faq-draft.ts'

function fields(overrides: Partial<FaqDraftFields> = {}): FaqDraftFields {
  return {
    question: 'Do you ship internationally?',
    answer: 'Yes, worldwide.',
    ...overrides,
  }
}

test('a complete draft has no errors', () => {
  assert.deepEqual(faqDraftErrors(fields()), {})
})

test('a question is required', () => {
  assert.equal(faqDraftErrors(fields({ question: '   ' })).question, 'Enter the question.')
})

test('an answer is required', () => {
  assert.equal(faqDraftErrors(fields({ answer: '' })).answer, 'Enter the answer.')
})

test('an absent question is required, same as a blank one', () => {
  assert.equal(faqDraftErrors(fields({ question: undefined })).question, 'Enter the question.')
})

test('an absent answer is required, same as a blank one', () => {
  assert.equal(faqDraftErrors(fields({ answer: undefined })).answer, 'Enter the answer.')
})

test('a question has a length limit', () => {
  const errors = faqDraftErrors(fields({ question: 'a'.repeat(FAQ_QUESTION_MAX_LENGTH + 1) }))
  assert.equal(errors.question, `Keep the question under ${FAQ_QUESTION_MAX_LENGTH} characters.`)
})

test('an answer has a length limit', () => {
  const errors = faqDraftErrors(fields({ answer: 'a'.repeat(FAQ_ANSWER_MAX_LENGTH + 1) }))
  assert.equal(errors.answer, `Keep the answer under ${FAQ_ANSWER_MAX_LENGTH} characters.`)
})

test('a question at the limit is fine', () => {
  assert.deepEqual(faqDraftErrors(fields({ question: 'a'.repeat(FAQ_QUESTION_MAX_LENGTH) })), {})
})

test('it trims the question and answer', () => {
  const draft = parseFaqDraft(fields({ question: '  Do you ship internationally?  ', answer: '  Yes.  ' }))
  assert.equal(draft.question, 'Do you ship internationally?')
  assert.equal(draft.answer, 'Yes.')
})

test('missing fields parse to empty strings', () => {
  const draft = parseFaqDraft({})
  assert.equal(draft.question, '')
  assert.equal(draft.answer, '')
})

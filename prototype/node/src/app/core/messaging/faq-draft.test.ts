import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  FAQ_ANSWER_MAX_LENGTH,
  FAQ_QUESTION_MAX_LENGTH,
  parseFaqDraft,
  type FaqDraftErrors,
  type FaqDraftFields,
} from './faq-draft.ts'

function fields(overrides: Partial<FaqDraftFields> = {}): FaqDraftFields {
  return {
    question: 'Do you ship internationally?',
    answer: 'Yes, worldwide.',
    ...overrides,
  }
}

function errorsOf(overrides: Partial<FaqDraftFields> = {}): FaqDraftErrors {
  const parsed = parseFaqDraft(fields(overrides))

  return parsed.ok ? {} : parsed.errors
}

test('a complete draft parses into a value', () => {
  const parsed = parseFaqDraft(fields())

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.question, 'Do you ship internationally?')
  assert.equal(parsed.value.answer, 'Yes, worldwide.')
})

test('a question is required', () => {
  assert.equal(errorsOf({ question: '   ' }).question, 'Enter the question.')
})

test('an answer is required', () => {
  assert.equal(errorsOf({ answer: '' }).answer, 'Enter the answer.')
})

test('an absent question is required, same as a blank one', () => {
  assert.equal(errorsOf({ question: undefined }).question, 'Enter the question.')
})

test('an absent answer is required, same as a blank one', () => {
  assert.equal(errorsOf({ answer: undefined }).answer, 'Enter the answer.')
})

test('a question has a length limit', () => {
  assert.equal(
    errorsOf({ question: 'a'.repeat(FAQ_QUESTION_MAX_LENGTH + 1) }).question,
    `Keep the question under ${FAQ_QUESTION_MAX_LENGTH} characters.`,
  )
})

test('an answer has a length limit', () => {
  assert.equal(
    errorsOf({ answer: 'a'.repeat(FAQ_ANSWER_MAX_LENGTH + 1) }).answer,
    `Keep the answer under ${FAQ_ANSWER_MAX_LENGTH} characters.`,
  )
})

test('a question at the limit is fine', () => {
  assert.deepEqual(errorsOf({ question: 'a'.repeat(FAQ_QUESTION_MAX_LENGTH) }), {})
})

test('it trims the question and answer', () => {
  const parsed = parseFaqDraft(fields({ question: '  Do you ship internationally?  ', answer: '  Yes.  ' }))

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.question, 'Do you ship internationally?')
  assert.equal(parsed.value.answer, 'Yes.')
})

test('a wholly empty submission names both fields and yields no draft', () => {
  const parsed = parseFaqDraft({})

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, { question: 'Enter the question.', answer: 'Enter the answer.' })
})

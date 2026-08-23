import { test } from 'node:test'
import assert from 'node:assert/strict'
import { MESSAGE_BODY_MAX_LENGTH, messageBodyError, parseMessageBody } from './message-body.ts'

test('a body is required', () => {
  assert.equal(messageBodyError(undefined), 'Write a message before sending.')
})

test('a whitespace-only body is required', () => {
  assert.equal(messageBodyError('   '), 'Write a message before sending.')
})

test('a body has a length limit', () => {
  const message = `A message is at most ${MESSAGE_BODY_MAX_LENGTH} characters.`
  assert.equal(messageBodyError('a'.repeat(MESSAGE_BODY_MAX_LENGTH + 1)), message)
})

test('a body at the limit is fine', () => {
  assert.equal(messageBodyError('a'.repeat(MESSAGE_BODY_MAX_LENGTH)), null)
})

test('a normal body has no error', () => {
  assert.equal(messageBodyError('Is this still available?'), null)
})

test('the stored body is trimmed', () => {
  assert.equal(parseMessageBody('  Is this still available?  '), 'Is this still available?')
})

test('a missing body parses to an empty string', () => {
  assert.equal(parseMessageBody(undefined), '')
})

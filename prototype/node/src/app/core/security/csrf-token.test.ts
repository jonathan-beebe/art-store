import { test } from 'node:test'
import assert from 'node:assert/strict'
import { csrfToken, isValidCsrfToken, submittedCsrfToken } from './csrf-token.ts'

const secret = 'test-cookie-secret-long-enough'
const sessionId = 'ses_01example00000000000000000'

test('csrfToken is deterministic for the same session id and secret', () => {
  assert.equal(csrfToken(sessionId, secret), csrfToken(sessionId, secret))
})

test('csrfToken differs for a different session id', () => {
  assert.notEqual(csrfToken(sessionId, secret), csrfToken('ses_other0000000000000000000', secret))
})

test('csrfToken differs for a different secret', () => {
  assert.notEqual(csrfToken(sessionId, secret), csrfToken(sessionId, 'a-different-cookie-secret'))
})

test('csrfToken is a 64-character hex digest', () => {
  assert.match(csrfToken(sessionId, secret), /^[0-9a-f]{64}$/)
})

test('isValidCsrfToken accepts the token derived from the same session id and secret', () => {
  assert.equal(isValidCsrfToken(csrfToken(sessionId, secret), sessionId, secret), true)
})

test('isValidCsrfToken refuses a token derived from another session id', () => {
  const foreign = csrfToken('ses_other0000000000000000000', secret)

  assert.equal(isValidCsrfToken(foreign, sessionId, secret), false)
})

test('isValidCsrfToken refuses a token derived from another secret', () => {
  const wrongSecret = csrfToken(sessionId, 'a-different-cookie-secret')

  assert.equal(isValidCsrfToken(wrongSecret, sessionId, secret), false)
})

test('isValidCsrfToken refuses an empty string', () => {
  assert.equal(isValidCsrfToken('', sessionId, secret), false)
})

test('isValidCsrfToken refuses a token of the wrong length rather than throwing', () => {
  assert.equal(isValidCsrfToken('too-short', sessionId, secret), false)
})

test('isValidCsrfToken refuses a token one character off', () => {
  const almost = `${csrfToken(sessionId, secret).slice(0, -1)}0`

  assert.equal(isValidCsrfToken(almost, sessionId, secret), false)
})

test('submittedCsrfToken reads a plain string field, the shape @fastify/formbody parses', () => {
  assert.equal(submittedCsrfToken({ _csrf_token: 'abc123' }), 'abc123')
})

test('submittedCsrfToken reads a multipart field part, the shape attachFieldsToBody parses', () => {
  assert.equal(
    submittedCsrfToken({ _csrf_token: { type: 'field', value: 'abc123' } }),
    'abc123',
  )
})

test('submittedCsrfToken is null for a missing field', () => {
  assert.equal(submittedCsrfToken({}), null)
})

test('submittedCsrfToken is null for a body that is not an object', () => {
  assert.equal(submittedCsrfToken(null), null)
  assert.equal(submittedCsrfToken(undefined), null)
  assert.equal(submittedCsrfToken('a string'), null)
})

test('submittedCsrfToken is null for a multipart file part, which carries no value', () => {
  assert.equal(
    submittedCsrfToken({ _csrf_token: { type: 'file', filename: 'x.txt' } }),
    null,
  )
})

test('submittedCsrfToken is null for a field of the wrong shape', () => {
  assert.equal(submittedCsrfToken({ _csrf_token: 12345 }), null)
  assert.equal(submittedCsrfToken({ _csrf_token: null }), null)
})

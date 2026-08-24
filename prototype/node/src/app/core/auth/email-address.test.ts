import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isEmailAddress, normalizeEmail, redactedEmail } from './email-address.ts'

test('normalizeEmail lowercases', () => {
  assert.equal(normalizeEmail('Artist@Example.COM'), 'artist@example.com')
})

test('normalizeEmail trims surrounding whitespace', () => {
  assert.equal(normalizeEmail('  artist@example.com\n'), 'artist@example.com')
})

test('normalizeEmail leaves an already-normal address unchanged', () => {
  assert.equal(normalizeEmail('artist@example.com'), 'artist@example.com')
})

test('normalizeEmail treats null as empty', () => {
  assert.equal(normalizeEmail(null), '')
})

test('normalizeEmail treats undefined as empty', () => {
  assert.equal(normalizeEmail(undefined), '')
})

test('isEmailAddress accepts a well-formed address', () => {
  assert.equal(isEmailAddress('artist@example.com'), true)
})

test('isEmailAddress rejects an address with no @', () => {
  assert.equal(isEmailAddress('artist.example.com'), false)
})

test('isEmailAddress rejects a domain with no dot', () => {
  assert.equal(isEmailAddress('artist@example'), false)
})

test('isEmailAddress rejects whitespace in the local part', () => {
  assert.equal(isEmailAddress('artist name@example.com'), false)
})

test('isEmailAddress rejects a blank string', () => {
  assert.equal(isEmailAddress('   '), false)
})

test('isEmailAddress rejects null', () => {
  assert.equal(isEmailAddress(null), false)
})

test('isEmailAddress accepts a unicode local part', () => {
  assert.equal(isEmailAddress('café@example.com'), true)
})

test('isEmailAddress accepts the shortest possible address', () => {
  assert.equal(isEmailAddress('a@b.c'), true)
})

test('isEmailAddress rejects a leading @ with no local part', () => {
  assert.equal(isEmailAddress('@example.com'), false)
})

test('isEmailAddress rejects a domain with a trailing dot and nothing after', () => {
  assert.equal(isEmailAddress('artist@example.'), false)
})

test('redactedEmail never contains the address it redacts', () => {
  assert.equal(redactedEmail('artist@example.com').includes('artist'), false)
})

test('redactedEmail is deterministic for the same address', () => {
  assert.equal(redactedEmail('artist@example.com'), redactedEmail('artist@example.com'))
})

test('redactedEmail ignores case and surrounding whitespace, like normalizeEmail', () => {
  assert.equal(redactedEmail('Artist@Example.COM'), redactedEmail('  artist@example.com  '))
})

test('redactedEmail differs for a different address', () => {
  assert.notEqual(redactedEmail('artist@example.com'), redactedEmail('other@example.com'))
})

test('redactedEmail is a 16-character hex digest', () => {
  assert.match(redactedEmail('artist@example.com'), /^[0-9a-f]{16}$/)
})

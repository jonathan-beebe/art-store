import { test } from 'node:test'
import assert from 'node:assert/strict'
import { normalizeEmail, isEmailAddress } from './email-address.ts'

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

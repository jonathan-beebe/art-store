import { test } from 'node:test'
import assert from 'node:assert/strict'
import { digestMagicLinkToken } from './magic-link-token.ts'

test('digestMagicLinkToken matches a known sha256 hex digest', () => {
  assert.equal(
    digestMagicLinkToken('abc'),
    'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
  )
})

test('digestMagicLinkToken is stable for the same input', () => {
  assert.equal(digestMagicLinkToken('token-1'), digestMagicLinkToken('token-1'))
})

test('digestMagicLinkToken differs for different input', () => {
  assert.notEqual(digestMagicLinkToken('token-1'), digestMagicLinkToken('token-2'))
})

test('digestMagicLinkToken is 64 lowercase hex characters', () => {
  assert.match(digestMagicLinkToken('token-1'), /^[0-9a-f]{64}$/)
})

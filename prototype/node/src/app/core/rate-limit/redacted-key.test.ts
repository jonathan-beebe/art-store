import { test } from 'node:test'
import assert from 'node:assert/strict'
import { redactedRateLimitKey } from './redacted-key.ts'

test('the same key always redacts to the same digest', () => {
  assert.equal(redactedRateLimitKey('ada@example.com'), redactedRateLimitKey('ada@example.com'))
})

test('different keys redact differently', () => {
  assert.notEqual(redactedRateLimitKey('ada@example.com'), redactedRateLimitKey('grace@example.com'))
})

test('the raw value never appears in the digest', () => {
  assert.doesNotMatch(redactedRateLimitKey('ada@example.com'), /ada|example|com/)
})

test('the digest is a short hex string', () => {
  assert.match(redactedRateLimitKey('192.0.2.1'), /^[0-9a-f]{16}$/)
})

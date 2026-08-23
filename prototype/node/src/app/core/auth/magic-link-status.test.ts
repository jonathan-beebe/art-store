import { test } from 'node:test'
import assert from 'node:assert/strict'
import { magicLinkStatus, magicLinkExpiresAt } from './magic-link-status.ts'

const issuedAt = new Date('2026-08-22T12:00:00.000Z')
const expiresAt = new Date('2026-08-22T12:15:00.000Z')

test('a fresh, unconsumed link is usable', () => {
  const now = new Date('2026-08-22T12:05:00.000Z')
  assert.equal(magicLinkStatus({ expiresAt, consumedAt: null }, now), 'usable')
})

test('exactly at expiresAt the link is expired', () => {
  assert.equal(magicLinkStatus({ expiresAt, consumedAt: null }, expiresAt), 'expired')
})

test('one millisecond after expiresAt the link is expired', () => {
  const now = new Date(expiresAt.getTime() + 1)
  assert.equal(magicLinkStatus({ expiresAt, consumedAt: null }, now), 'expired')
})

test('a link consumed before expiry is consumed', () => {
  const consumedAt = new Date('2026-08-22T12:05:00.000Z')
  const now = new Date('2026-08-22T12:06:00.000Z')
  assert.equal(magicLinkStatus({ expiresAt, consumedAt }, now), 'consumed')
})

test('a link consumed before expiry stays consumed after expiry passes', () => {
  const consumedAt = new Date('2026-08-22T12:05:00.000Z')
  const now = new Date(expiresAt.getTime() + 1)
  assert.equal(magicLinkStatus({ expiresAt, consumedAt }, now), 'consumed')
})

test('magicLinkExpiresAt is 15 minutes after issuedAt', () => {
  assert.equal(magicLinkExpiresAt(issuedAt).toISOString(), '2026-08-22T12:15:00.000Z')
})

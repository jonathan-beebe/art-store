import { test } from 'node:test'
import assert from 'node:assert/strict'
import { blockedShopperNotice } from './blocked-shopper-notice.ts'

test('it names what the hold takes away', () => {
  const notice = blockedShopperNotice({ isBlocked: true, reason: null })

  assert.equal(notice, 'Your account is on hold, so you cannot add to a cart or check out.')
})

test('it carries the reason the admin gave', () => {
  const notice = blockedShopperNotice({ isBlocked: true, reason: 'Chargeback fraud.' })

  assert.match(notice, /Chargeback fraud\.$/)
})

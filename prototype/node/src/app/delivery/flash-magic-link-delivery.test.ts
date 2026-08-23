import { test } from 'node:test'
import assert from 'node:assert/strict'
import { flashMagicLinkDelivery } from './flash-magic-link-delivery.ts'

test('it hands the link to the debug alert the layouts render', () => {
  const flash = flashMagicLinkDelivery.deliver({
    email: 'artist@example.com',
    url: 'http://localhost:4000/auth/magic/abc',
    actorType: 'seller',
  })

  assert.deepEqual(flash, { debugMagicLink: 'http://localhost:4000/auth/magic/abc' })
})

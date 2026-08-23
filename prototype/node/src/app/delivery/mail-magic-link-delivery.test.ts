import { test } from 'node:test'
import assert from 'node:assert/strict'
import { NotImplementedError } from '../not-implemented-error.ts'
import { mailMagicLinkDelivery } from './mail-magic-link-delivery.ts'

test('it refuses to send until email is wired up', () => {
  assert.throws(
    () =>
      mailMagicLinkDelivery.deliver({
        email: 'artist@example.com',
        url: 'http://localhost:4000/auth/magic/abc',
        actorType: 'seller',
      }),
    new NotImplementedError('Email delivery is not implemented yet'),
  )
})

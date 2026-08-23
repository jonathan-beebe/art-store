import { test } from 'node:test'
import assert from 'node:assert/strict'
import { flashMagicLinkDelivery } from './flash-magic-link-delivery.ts'
import { openCommerceWorld } from '../test/commerce-world.ts'

const LINK = {
  email: 'artist@example.com',
  url: 'http://localhost:4000/auth/magic/abc',
  actorType: 'seller',
} as const

test('it hands the link to the debug alert the layouts render', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const flash = await flashMagicLinkDelivery.deliver(world.context, LINK)

  assert.deepEqual(flash, { debugMagicLink: 'http://localhost:4000/auth/magic/abc' })
})

test('it queues nothing: the link never leaves the request', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await flashMagicLinkDelivery.deliver(world.context, LINK)

  const queued = await world.db.selectFrom('outboxMessages').selectAll().execute()
  assert.equal(queued.length, 0)
})

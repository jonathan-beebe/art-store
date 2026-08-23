import { test } from 'node:test'
import assert from 'node:assert/strict'
import { outboxMagicLinkDelivery } from './outbox-magic-link-delivery.ts'
import { openCommerceWorld, PLACED_AT } from '../test/commerce-world.ts'

const LINK = {
  email: 'artist@example.com',
  url: 'http://localhost:4000/auth/magic/abc',
  actorType: 'seller',
} as const

test('it queues one message addressed to the person who asked', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await outboxMagicLinkDelivery.deliver(world.context, LINK)

  const queued = await world.db.selectFrom('outboxMessages').selectAll().execute()
  assert.equal(queued.length, 1)
  assert.equal(queued[0]?.recipient, 'artist@example.com')
  assert.equal(queued[0]?.subject, 'Your Art Store sign-in link')
  assert.equal(queued[0]?.url, 'http://localhost:4000/auth/magic/abc')
})

test('the queued message is undelivered and stamped with the clock', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await outboxMagicLinkDelivery.deliver(world.context, LINK)

  const queued = await world.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.equal(queued.deliveredAt, null)
  assert.equal(queued.createdAt, PLACED_AT.toISOString())
})

test('it flashes nothing, so no page prints the link', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const flash = await outboxMagicLinkDelivery.deliver(world.context, LINK)

  assert.deepEqual(flash, {})
})

test('a rolled-back transaction queues no message', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await assert.rejects(() =>
    world.db.transaction().execute(async (transaction) => {
      await outboxMagicLinkDelivery.deliver({ ...world.context, db: transaction }, LINK)
      throw new Error('the request failed after the link was issued')
    }),
  )

  const queued = await world.db.selectFrom('outboxMessages').selectAll().execute()
  assert.equal(queued.length, 0)
})

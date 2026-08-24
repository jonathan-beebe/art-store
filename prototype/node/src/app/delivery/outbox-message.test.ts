import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../test/fixture-ids.ts'
import { enqueueOutboxMessage, renderOutboxMessage } from './outbox-message.ts'
import type { OutboxMessage } from '../db/commerce-schema.ts'
import { openCommerceWorld, PLACED_AT } from '../test/commerce-world.ts'

const ROW: OutboxMessage = {
  id: fixtureId('obx', 12),
  recipient: 'artist@example.com',
  subject: 'Item sold',
  body: 'Order 7 is paid.',
  url: 'http://localhost:4000/seller/orders/7',
  createdAt: '2026-08-24T12:00:00.000Z',
  deliveredAt: null,
}

test('a rendered message identifies itself by row id and carries the row’s instant', () => {
  const rendered = renderOutboxMessage(ROW)

  assert.match(rendered, new RegExp(`^Message-ID: <outbox-${ROW.id}@art-store\\.example>$`, 'm'))
  assert.match(rendered, /^Date: Mon, 24 Aug 2026 12:00:00 \+0000$/m)
  assert.match(rendered, /^To: artist@example\.com$/m)
})

test('a message with no url renders without one', () => {
  const rendered = renderOutboxMessage({ ...ROW, url: null })

  assert.equal(rendered.includes('http://'), false)
})

test('enqueuing stores the message pending, stamped with the clock', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await enqueueOutboxMessage(world.context, {
    recipient: 'artist@example.com',
    message: { subject: 'Item sold', body: 'Order 7 is paid.', url: null },
  })

  const queued = await world.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.equal(queued.recipient, 'artist@example.com')
  assert.equal(queued.body, 'Order 7 is paid.')
  assert.equal(queued.url, null)
  assert.equal(queued.deliveredAt, null)
  assert.equal(queued.createdAt, PLACED_AT.toISOString())
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../../test/fixture-ids.ts'
import { outboxRow, outboxRows } from './outbox-rows.ts'
import { enqueueOutboxMessage } from '../../../delivery/outbox-message.ts'
import { openCommerceWorld } from '../../../test/commerce-world.ts'

test('rows come back newest first', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  for (const subject of ['Item sold', 'Order shipped', 'New message']) {
    await enqueueOutboxMessage(world.context, {
      recipient: 'artist@example.com',
      message: { subject, body: 'Body.', url: null },
    })
  }

  const rows = await outboxRows(world.context)

  assert.deepEqual(
    rows.map((row) => row.subject),
    ['New message', 'Order shipped', 'Item sold'],
  )
})

test('an empty outbox lists nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await outboxRows(world.context), [])
})

test('one row comes back by id', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await enqueueOutboxMessage(world.context, {
    recipient: 'artist@example.com',
    message: { subject: 'Item sold', body: 'Body.', url: '/seller/orders/7' },
  })
  const [queued] = await outboxRows(world.context)

  const row = await outboxRow(world.context, queued?.id ?? fixtureId('obx', 0))

  assert.equal(row?.subject, 'Item sold')
  assert.equal(row?.url, '/seller/orders/7')
})

test('an id naming nothing is null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.equal(await outboxRow(world.context, fixtureId('obx', 404)), null)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../../test/fixture-ids.ts'
import { countOutboxRows, outboxRow, outboxRows } from './outbox-rows.ts'
import { enqueueOutboxMessage } from '../../../delivery/outbox-message.ts'
import { openCommerceWorld } from '../../../test/commerce-world.ts'

const FULL_PAGE = { offset: 0, limit: 100 }

test('rows come back newest first', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  for (const subject of ['Item sold', 'Order shipped', 'New message']) {
    await enqueueOutboxMessage(world.context, {
      recipient: 'artist@example.com',
      message: { subject, body: 'Body.', url: null },
    })
  }

  const rows = await outboxRows(world.context, FULL_PAGE)

  assert.deepEqual(
    rows.map((row) => row.subject),
    ['New message', 'Order shipped', 'Item sold'],
  )
})

test('an empty outbox lists nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await outboxRows(world.context, FULL_PAGE), [])
  assert.equal(await countOutboxRows(world.context), 0)
})

test('one row comes back by id', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await enqueueOutboxMessage(world.context, {
    recipient: 'artist@example.com',
    message: { subject: 'Item sold', body: 'Body.', url: '/seller/orders/7' },
  })
  const [queued] = await outboxRows(world.context, FULL_PAGE)

  const row = await outboxRow(world.context, queued?.id ?? fixtureId('obx', 0))

  assert.equal(row?.subject, 'Item sold')
  assert.equal(row?.url, '/seller/orders/7')
})

test('an id naming nothing is null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.equal(await outboxRow(world.context, fixtureId('obx', 404)), null)
})

test('the page offset and limit slice the queued messages', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  for (const subject of ['First', 'Second', 'Third']) {
    await enqueueOutboxMessage(world.context, {
      recipient: 'artist@example.com',
      message: { subject, body: 'Body.', url: null },
    })
  }

  const firstPage = await outboxRows(world.context, { offset: 0, limit: 2 })
  assert.deepEqual(firstPage.map((row) => row.subject), ['Third', 'Second'])

  const secondPage = await outboxRows(world.context, { offset: 2, limit: 2 })
  assert.deepEqual(secondPage.map((row) => row.subject), ['First'])
})

test('countOutboxRows counts every queued message, not just the page', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  for (const subject of ['First', 'Second', 'Third']) {
    await enqueueOutboxMessage(world.context, {
      recipient: 'artist@example.com',
      message: { subject, body: 'Body.', url: null },
    })
  }

  assert.equal(await countOutboxRows(world.context), 3)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, readFile, readdir, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { drainOutbox } from './drain-outbox.ts'
import { enqueueOutboxMessage } from '../../delivery/outbox-message.ts'
import { openCommerceWorld, type CommerceWorld } from '../../test/commerce-world.ts'

const DRAINED_AT = new Date('2026-08-25T09:00:00.000Z')

async function temporaryOutboxDir(t: { after(fn: () => unknown): void }): Promise<string> {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-outbox-'))
  t.after(() => rm(dir, { recursive: true, force: true }))

  return path.join(dir, 'outbox')
}

async function queue(world: CommerceWorld, subject: string, url: string | null): Promise<void> {
  await enqueueOutboxMessage(world.context, {
    recipient: 'artist@example.com',
    message: { subject, body: `${subject} happened.`, url },
  })
}

test('it writes one file per pending message, named for the row', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const outboxDir = await temporaryOutboxDir(t)

  await queue(world, 'Item sold', '/seller/orders/7')
  await queue(world, 'Order shipped', null)

  const drained = await drainOutbox(world.context, { outboxDir })

  assert.equal(drained.length, 2)
  assert.deepEqual(
    (await readdir(outboxDir)).sort(),
    drained.map((message) => `${message.id}.eml`).sort(),
  )
  assert.equal(drained[0]?.file, path.join(outboxDir, `${drained[0]?.id}.eml`))
  assert.equal(drained[0]?.recipient, 'artist@example.com')
  assert.equal(drained[0]?.subject, 'Item sold')
})

test('the file holds the rendered message', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const outboxDir = await temporaryOutboxDir(t)

  await queue(world, 'Item sold', 'http://localhost:4000/seller/orders/7')
  const [sold] = await drainOutbox(world.context, { outboxDir })

  const written = await readFile(path.join(outboxDir, `${sold?.id}.eml`), 'utf8')

  assert.match(written, /^Subject: Item sold\r$/m)
  assert.match(written, /^To: artist@example\.com\r$/m)
  assert.equal(written.endsWith('http://localhost:4000/seller/orders/7\r\n'), true)
})

test('it stamps every message it wrote with the clock', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const outboxDir = await temporaryOutboxDir(t)

  await queue(world, 'Item sold', null)
  world.travelTo(DRAINED_AT)
  await drainOutbox(world.context, { outboxDir })

  const rows = await world.db.selectFrom('outboxMessages').selectAll().execute()
  assert.equal(rows[0]?.deliveredAt, DRAINED_AT.toISOString())
})

test('a second drain writes nothing: delivered messages are not pending', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const outboxDir = await temporaryOutboxDir(t)

  await queue(world, 'Item sold', null)
  const [sold] = await drainOutbox(world.context, { outboxDir })

  const second = await drainOutbox(world.context, { outboxDir })

  assert.deepEqual(second, [])
  assert.deepEqual(await readdir(outboxDir), [`${sold?.id}.eml`])
})

test('an empty outbox creates no directory and writes nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const outboxDir = await temporaryOutboxDir(t)

  const drained = await drainOutbox(world.context, { outboxDir })

  assert.deepEqual(drained, [])
  await assert.rejects(() => readdir(outboxDir))
})

test('it drains a message queued after an earlier drain', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const outboxDir = await temporaryOutboxDir(t)

  await queue(world, 'Item sold', null)
  const [sold] = await drainOutbox(world.context, { outboxDir })
  await queue(world, 'Order shipped', null)

  const drained = await drainOutbox(world.context, { outboxDir })

  assert.equal(drained.length, 1)
  assert.notEqual(drained[0]?.id, sold?.id)
  assert.equal(drained[0]?.subject, 'Order shipped')
})

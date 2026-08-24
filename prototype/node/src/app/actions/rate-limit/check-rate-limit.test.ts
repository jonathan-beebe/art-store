import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { checkRateLimit } from './check-rate-limit.ts'
import { openCommerceWorld } from '../../test/commerce-world.ts'
import { openDatabase } from '../../db/database.ts'
import { migrateToLatest } from '../../db/migrator.ts'

test('a count at or under the limit does not trip', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const limit = { count: 3, windowSeconds: 900 }

  await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  const third = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })

  assert.deepEqual(third, { tripped: false })
})

test('the request that exceeds the limit trips it', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const limit = { count: 2, windowSeconds: 900 }

  await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  const third = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })

  assert.equal(third.tripped, true)
  if (!third.tripped) throw new Error('expected a trip')
  assert.equal(third.retryAfterSeconds > 0, true)
})

test('off never trips, however many requests come through', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  for (let i = 0; i < 10; i += 1) {
    const decision = await checkRateLimit(world.context, 'off', { name: 'checkout', key: 'cus_1' })
    assert.deepEqual(decision, { tripped: false })
  }

  const rows = await world.db.selectFrom('rateLimitWindows').selectAll().execute()
  assert.equal(rows.length, 0)
})

test('different keys count separately', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const limit = { count: 1, windowSeconds: 900 }

  const first = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  const second = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_2' })

  assert.deepEqual(first, { tripped: false })
  assert.deepEqual(second, { tripped: false })
})

test('different limit names count separately even for the same key', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const limit = { count: 1, windowSeconds: 900 }

  const first = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'actor_1' })
  const second = await checkRateLimit(world.context, limit, { name: 'listing_write', key: 'actor_1' })

  assert.deepEqual(first, { tripped: false })
  assert.deepEqual(second, { tripped: false })
})

test('a new window resets the count', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const limit = { count: 1, windowSeconds: 900 }

  await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  const trippedInWindow = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  assert.equal(trippedInWindow.tripped, true)

  world.travelTo(new Date('2026-08-20T09:15:00.000Z'))

  const nextWindow = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  assert.deepEqual(nextWindow, { tripped: false })
})

test('the counter survives a restart: a second instance over the same file reads it back', async (t) => {
  const directory = await mkdtemp(path.join(tmpdir(), 'art-store-rate-limit-'))
  t.after(() => rm(directory, { recursive: true, force: true }))
  const file = path.join(directory, 'test.sqlite3')

  const clock = { now: () => new Date('2026-08-20T09:00:00.000Z') }
  const limit = { count: 2, windowSeconds: 900 }

  const first = openDatabase(file)
  await migrateToLatest(first)
  await checkRateLimit({ db: first, clock }, limit, { name: 'payment_attempt', key: 'ord_1' })
  await checkRateLimit({ db: first, clock }, limit, { name: 'payment_attempt', key: 'ord_1' })
  await first.destroy()

  const second = openDatabase(file)
  t.after(() => second.destroy())

  const third = await checkRateLimit({ db: second, clock }, limit, { name: 'payment_attempt', key: 'ord_1' })

  assert.equal(third.tripped, true)
})

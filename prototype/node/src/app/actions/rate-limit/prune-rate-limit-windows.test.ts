import { test } from 'node:test'
import assert from 'node:assert/strict'
import { pruneRateLimitWindows } from './prune-rate-limit-windows.ts'
import { checkRateLimit } from './check-rate-limit.ts'
import { openCommerceWorld } from '../../test/commerce-world.ts'
import type { RateLimit } from '../../core/rate-limit/rate-limit-value.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { newId } from '../../ids.ts'

const ASOF = new Date('2026-08-23T18:00:00.000Z')

/** A `rateLimitWindows` row written straight through Kysely, the way a real
 * upsert would leave one, so a test can plant a row at an exact windowStart. */
async function seedWindow(db: AppDatabase, windowStart: Date, name = 'checkout' as const) {
  await db
    .insertInto('rateLimitWindows')
    .values({
      id: newId('rlw', windowStart),
      name,
      key: 'cus_1',
      windowStart: toTimestamp(windowStart),
      count: 1,
    })
    .execute()
}

test('a row strictly before the cutoff is deleted; one at the cutoff and one inside the largest window survive', async (t) => {
  const world = await openCommerceWorld(ASOF)
  t.after(world.close)

  const limits: RateLimit[] = [
    { count: 5, windowSeconds: 900 },
    { count: 5, windowSeconds: 3600 },
  ]

  await seedWindow(world.db, new Date('2026-08-23T16:59:00.000Z')) // before 17:00 cutoff
  await seedWindow(world.db, new Date('2026-08-23T17:00:00.000Z')) // exactly at the cutoff
  await seedWindow(world.db, new Date('2026-08-23T17:30:00.000Z')) // inside the largest window

  const deleted = await pruneRateLimitWindows(world.context, { limits, asOf: ASOF })

  assert.equal(deleted, 1)
  const remaining = await world.db.selectFrom('rateLimitWindows').select('windowStart').execute()
  assert.deepEqual(
    remaining.map((row) => row.windowStart).sort(),
    ['2026-08-23T17:00:00.000Z', '2026-08-23T17:30:00.000Z'],
  )
})

test('a limit tripped a moment ago is not forgiven by a prune run at the same instant', async (t) => {
  const world = await openCommerceWorld(ASOF)
  t.after(world.close)

  const limit: RateLimit = { count: 2, windowSeconds: 900 }
  const limits: RateLimit[] = [limit, { count: 5, windowSeconds: 3600 }]

  await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  const tripped = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  assert.equal(tripped.tripped, true)

  await pruneRateLimitWindows(world.context, { limits, asOf: world.context.clock.now() })

  const again = await checkRateLimit(world.context, limit, { name: 'checkout', key: 'cus_1' })
  assert.equal(again.tripped, true)
})

test('a window older than its own limit but newer than the largest window survives', async (t) => {
  const world = await openCommerceWorld(ASOF)
  t.after(world.close)

  // 900s (15m) is the smaller limit; a row 30m old has already aged out of it,
  // but the cutoff tracks the 3600s (1h) limit, so it is not yet reachable.
  const limits: RateLimit[] = [
    { count: 5, windowSeconds: 900 },
    { count: 5, windowSeconds: 3600 },
  ]

  await seedWindow(world.db, new Date('2026-08-23T17:30:00.000Z'))

  const deleted = await pruneRateLimitWindows(world.context, { limits, asOf: ASOF })

  assert.equal(deleted, 0)
  const remaining = await world.db.selectFrom('rateLimitWindows').selectAll().execute()
  assert.equal(remaining.length, 1)
})

test('every limit off leaves every row alone and deletes nothing', async (t) => {
  const world = await openCommerceWorld(ASOF)
  t.after(world.close)

  await seedWindow(world.db, new Date('2000-01-01T00:00:00.000Z'))

  const deleted = await pruneRateLimitWindows(world.context, { limits: ['off', 'off'], asOf: ASOF })

  assert.equal(deleted, 0)
  const remaining = await world.db.selectFrom('rateLimitWindows').selectAll().execute()
  assert.equal(remaining.length, 1)
})

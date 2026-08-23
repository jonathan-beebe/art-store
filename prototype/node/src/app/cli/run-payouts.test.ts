import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { main } from './run-payouts.ts'
import { confirmDelivered } from '../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../actions/fulfillments/mark-shipped.ts'
import { finalizeOrder } from '../actions/orders/finalize-order.ts'
import { fixedClock } from '../clock.ts'
import { openDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'
import {
  APPROVED_CARD,
  createCustomer,
  createListing,
  createSeller,
  placedOrder,
} from '../test/commerce-world.ts'

/**
 * `main` opens its own database connection from `env`, so this exercises it
 * against a real file on disk — the setup connection is closed before `main`
 * runs, the way `npm run migrate` and `npm run payouts` never share a process.
 */
test('main prints and pays a seller whose delivery landed inside the settled period', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-run-payouts-'))
  const databaseFile = path.join(dir, 'test.sqlite3')
  t.after(() => rm(dir, { recursive: true, force: true }))

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)

  const placedAt = fixedClock(new Date('2026-08-17T09:00:00.000Z'))
  const shippedAt = fixedClock(new Date('2026-08-18T09:00:00.000Z'))
  const deliveredAt = fixedClock(new Date('2026-08-19T09:00:00.000Z'))

  const sellerId = await createSeller({ db: setupDb, clock: placedAt })
  const buyerId = await createCustomer({ db: setupDb, clock: placedAt })
  const listing = await createListing({ db: setupDb, clock: placedAt }, sellerId, { priceCents: 45_000 })
  const order = await placedOrder({ db: setupDb, clock: placedAt }, buyerId, [listing.id])
  await finalizeOrder({ db: setupDb, clock: placedAt }, { orderId: order.id, cardNumber: APPROVED_CARD })

  const fulfillment = await setupDb
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  await markShipped(
    { db: setupDb, clock: shippedAt },
    { fulfillmentId: fulfillment.id, carrier: 'USPS', trackingNumber: '9400111899' },
  )
  await confirmDelivered({ db: setupDb, clock: deliveredAt }, fulfillment.id)
  await setupDb.destroy()

  const lines: string[] = []
  const originalLog = console.log
  console.log = (line: unknown) => {
    lines.push(String(line))
  }
  t.after(() => {
    console.log = originalLog
  })

  await main(['node', 'run-payouts.ts', '--as-of=2026-08-24'], { DATABASE_FILE: databaseFile })

  assert.equal(lines[0], 'Payout period 2026-08-17 to 2026-08-23')
  assert.equal(lines.includes(`seller ${sellerId} $405.00`), true)
  assert.equal(lines.at(-1), '1 seller(s) paid.')
})

test('main reports no payout when nothing has released escrow', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-run-payouts-'))
  const databaseFile = path.join(dir, 'test.sqlite3')
  t.after(() => rm(dir, { recursive: true, force: true }))

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()

  const lines: string[] = []
  const originalLog = console.log
  console.log = (line: unknown) => {
    lines.push(String(line))
  }
  t.after(() => {
    console.log = originalLog
  })

  await main(['node', 'run-payouts.ts', '--as-of=2026-08-24'], { DATABASE_FILE: databaseFile })

  assert.equal(lines.at(-1), 'No seller has a released balance for this period.')
})

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
import { createCliLogger } from '../logging.ts'
import {
  APPROVED_CARD,
  createCustomer,
  createListing,
  createSeller,
  placedOrder,
} from '../test/commerce-world.ts'
import { captureLogLines } from '../test/log-lines.ts'

/**
 * `main` opens its own database connection from `env`, so this exercises it
 * against a real file on disk — the setup connection is closed before `main`
 * runs, the way `npm run migrate` and `npm run payouts` never share a process.
 */
test('main logs one line per seller paid and a summary', async (t) => {
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

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info' }, { stream })

  await main(['node', 'run-payouts.ts', '--as-of=2026-08-24'], { DATABASE_FILE: databaseFile }, logger)

  const lines = stream.lines()
  const paidLine = lines.find((line) => line.event === 'payout.paid')
  assert.equal(paidLine?.sellerId, sellerId)
  assert.equal(paidLine?.amountCents, 40_500)
  assert.equal(paidLine?.period, '2026-08-17 to 2026-08-23')

  const summaryLine = lines.find((line) => line.event === 'payout.run')
  assert.equal(summaryLine?.count, 1)
  assert.equal(summaryLine?.totalCents, 40_500)
})

test('main logs a zero-count summary when nothing has released escrow', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-run-payouts-'))
  const databaseFile = path.join(dir, 'test.sqlite3')
  t.after(() => rm(dir, { recursive: true, force: true }))

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info' }, { stream })

  await main(['node', 'run-payouts.ts', '--as-of=2026-08-24'], { DATABASE_FILE: databaseFile }, logger)

  const lines = stream.lines()
  assert.equal(lines.some((line) => line.event === 'payout.paid'), false)
  const summaryLine = lines.find((line) => line.event === 'payout.run')
  assert.equal(summaryLine?.count, 0)
  assert.equal(summaryLine?.totalCents, 0)
})

test('main logs the error and sets a failing exit code when the run itself fails', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-run-payouts-'))
  const databaseFile = path.join(dir, 'test.sqlite3')
  t.after(() => rm(dir, { recursive: true, force: true }))
  // No migrations applied, so the query inside `runWeeklyPayout` fails against
  // a database with no tables — the run itself failing, not a usage mistake.

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info' }, { stream })
  const exitCodeBefore = process.exitCode
  t.after(() => {
    process.exitCode = exitCodeBefore
  })

  await main(['node', 'run-payouts.ts', '--as-of=2026-08-24'], { DATABASE_FILE: databaseFile }, logger)

  assert.equal(process.exitCode, 1)
  assert.equal(stream.lines().some((line) => line.err !== undefined), true)
})

test('a flag the command does not take is a mistake, not a logged failure', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-run-payouts-'))
  const databaseFile = path.join(dir, 'test.sqlite3')
  t.after(() => rm(dir, { recursive: true, force: true }))

  await assert.rejects(() =>
    main(['node', 'run-payouts.ts', '--evrything'], { DATABASE_FILE: databaseFile }),
  )
})

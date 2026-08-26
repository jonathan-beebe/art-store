import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { main } from './sweep-stale-orders.ts'
import { markAwaitingPayment } from '../actions/orders/mark-awaiting-payment.ts'
import { fixedClock } from '../clock.ts'
import { openDatabase, type AppDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'
import { openLogStore } from '../log-store.ts'
import { createCliLogger, defaultLogStore } from '../logging.ts'
import { toTimestamp } from '../db/timestamp.ts'
import { newId } from '../ids.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  placedOrder,
} from '../test/commerce-world.ts'
import { captureLogLines, storedLogLine } from '../test/log-lines.ts'

const PLACED_AT = new Date('2026-08-20T09:00:00.000Z')

async function temporaryDatabaseFile(t: { after: (fn: () => unknown) => void }): Promise<string> {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-sweep-'))
  t.after(() => rm(dir, { recursive: true, force: true }))

  return path.join(dir, 'test.sqlite3')
}

/** One unverified order sitting on a listing's only copy, placed on the fixture day. */
async function seedUnverifiedOrder(db: AppDatabase) {
  const clock = fixedClock(PLACED_AT)
  const sellerId = await createSeller({ db, clock })
  const buyerId = await createCustomer({ db, clock }, { isVerified: false })
  const listing = await createListing({ db, clock }, sellerId)

  return {
    order: await placedOrder({ db, clock }, buyerId, [listing.id], { isVerified: false }),
    listingId: listing.id,
  }
}

/** A `rateLimitWindows` row written straight through Kysely, at an exact `windowStart`. */
async function seedRateLimitWindow(db: AppDatabase, windowStart: Date) {
  await db
    .insertInto('rateLimitWindows')
    .values({
      id: newId('rlw', windowStart),
      name: 'checkout',
      key: 'cus_1',
      windowStart: toTimestamp(windowStart),
      count: 1,
    })
    .execute()
}

test('main cancels the stale orders and says how many', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  const { order } = await seedUnverifiedOrder(setupDb)
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'], { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: 'off' }, logger)

  assert.equal(stream.data('order.sweep', 'did').count, 1)
  assert.equal(stream.data('order.sweep', 'doing').order_id, order.id)
  assert.equal(stream.line('order.sweep', 'did').actor_type, 'system')
  assert.equal(stream.line('order.cancel', 'did').actor_type, 'system')
  assert.match(String(stream.line('order.sweep', 'will').msg), /^🎬 /)
  assert.doesNotMatch(String(stream.line('order.cancel', 'will').msg), /^(🎬|🟢|⚠️|🛑|❌)/)

  const db = openDatabase(databaseFile)
  const swept = await db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', order.id)
    .executeTakeFirstOrThrow()
  assert.equal(swept.status, 'cancelled')
  await db.destroy()
})

test('main leaves an order that is only awaiting payment alone', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  const { order } = await seedUnverifiedOrder(setupDb)
  await markAwaitingPayment({ db: setupDb, clock: fixedClock(PLACED_AT) }, order.id)
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'], { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: 'off' }, logger)

  assert.equal(stream.data('order.sweep', 'did').count, 0)
  assert.deepEqual(stream.linesFor('order.cancel'), [])
})

test('STALE_ORDER_HOURS decides how far back the sweep reaches', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await seedUnverifiedOrder(setupDb)
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  // The order was placed on the 20th, so a window of 30 days does not reach it.
  await main(
    ['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'],
    { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: 'off', STALE_ORDER_HOURS: '720' },
    logger,
  )

  assert.equal(stream.data('order.sweep', 'did').count, 0)
})

test('main logs the error and sets a failing exit code when the sweep itself fails', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  // No migrations applied, so the query inside the sweep fails against a
  // database with no tables — the run itself failing, not a usage mistake.

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })
  const exitCodeBefore = process.exitCode
  t.after(() => {
    process.exitCode = exitCodeBefore
  })

  await main(['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'], { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: 'off' }, logger)

  assert.equal(process.exitCode, 1)
  const failed = stream.line('order.sweep', 'failed')
  assert.equal(failed.level, 'error')
  assert.match(String(failed.msg), /^❌ /)
})

test('main also prunes rate-limit windows the largest configured limit can no longer read', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  // --as-of=2026-08-23 is midnight UTC; the default limits' largest window is
  // 1h, so 2026-08-22T23:00:00.000Z is the cutoff.
  await seedRateLimitWindow(setupDb, new Date('2020-01-01T00:00:00.000Z'))
  await seedRateLimitWindow(setupDb, new Date('2026-08-22T23:30:00.000Z'))
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'], { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: 'off' }, logger)

  const db = openDatabase(databaseFile)
  const rows = await db.selectFrom('rateLimitWindows').select('windowStart').execute()
  assert.deepEqual(
    rows.map((row) => row.windowStart),
    ['2026-08-22T23:30:00.000Z'],
  )
  await db.destroy()
})

/** A logs file beside the commerce one, so each test's store handle is its own. */
async function temporaryLogsFile(t: { after: (fn: () => unknown) => void }): Promise<string> {
  return `${await temporaryDatabaseFile(t)}.logs`
}

/** Stored log rows planted at exact `ts` values, through the store's own writer. */
function seedLogLines(file: string, tsValues: readonly string[]): void {
  const store = openLogStore(file)
  for (const ts of tsValues) store.append(storedLogLine({ ts }))
  store.flushSync()
  store.close()
}

function storedLogTs(file: string): string[] {
  const db = new DatabaseSync(file)
  const rows = db.prepare('SELECT ts FROM log_lines ORDER BY ts').all() as Array<{ ts: string }>
  db.close()

  return rows.map((row) => row.ts)
}

test('main also prunes stored log lines older than the retention window before as-of', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  const logsFile = await temporaryLogsFile(t)
  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()
  // --as-of=2026-08-23 is midnight UTC; the default retention is 14 days, so
  // 2026-08-09T00:00:00.000Z is the cutoff.
  seedLogLines(logsFile, ['2026-08-08T23:59:59.999Z', '2026-08-09T00:00:00.000Z'])

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(
    ['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'],
    { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: logsFile },
    logger,
  )

  assert.deepEqual(storedLogTs(logsFile), ['2026-08-09T00:00:00.000Z'])
})

test('LOG_RETENTION_DAYS=off leaves every stored log line alone', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  const logsFile = await temporaryLogsFile(t)
  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()
  seedLogLines(logsFile, ['2000-01-01T00:00:00.000Z'])

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(
    ['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'],
    { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: logsFile, LOG_RETENTION_DAYS: 'off' },
    logger,
  )

  assert.deepEqual(storedLogTs(logsFile), ['2000-01-01T00:00:00.000Z'])
})

test('a failing log prune sets the exit code and the swept orders stay cancelled', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)
  const logsFile = await temporaryLogsFile(t)
  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  const { order } = await seedUnverifiedOrder(setupDb)
  await setupDb.destroy()

  // The CLI reuses the per-file store its logger opened; closing that store
  // first leaves the prune a handle every statement refuses.
  const store = defaultLogStore({ logLevel: 'info', environment: 'test', logDatabaseFile: logsFile })
  assert.ok(store !== undefined)
  store.close()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })
  const exitCodeBefore = process.exitCode
  t.after(() => {
    process.exitCode = exitCodeBefore
  })

  await main(
    ['node', 'sweep-stale-orders.ts', '--as-of=2026-08-23'],
    { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: logsFile },
    logger,
  )

  assert.equal(process.exitCode, 1)
  assert.equal(stream.data('order.sweep', 'did').count, 1)
  const db = openDatabase(databaseFile)
  const swept = await db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', order.id)
    .executeTakeFirstOrThrow()
  assert.equal(swept.status, 'cancelled')
  await db.destroy()
})

test('a flag the command does not take is a mistake, not a logged failure', async (t) => {
  const databaseFile = await temporaryDatabaseFile(t)

  await assert.rejects(() =>
    main(['node', 'sweep-stale-orders.ts', '--evrything'], { DATABASE_FILE: databaseFile, LOG_DATABASE_FILE: 'off' }),
  )
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, readdir, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { main } from './drain-outbox.ts'
import { systemClock } from '../clock.ts'
import { openDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'
import { enqueueOutboxMessage } from '../delivery/outbox-message.ts'
import { createCliLogger } from '../logging.ts'
import { captureLogLines } from '../test/log-lines.ts'

async function temporaryDatabase(t: { after(fn: () => unknown): void }): Promise<string> {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-drain-outbox-'))
  t.after(() => rm(dir, { recursive: true, force: true }))

  return path.join(dir, 'test.sqlite3')
}

test('main logs one structured line per drained message and says where they landed', async (t) => {
  const databaseFile = await temporaryDatabase(t)
  const outboxDir = path.join(path.dirname(databaseFile), 'outbox')

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await enqueueOutboxMessage(
    { db: setupDb, clock: systemClock },
    { recipient: 'artist@example.com', message: { subject: 'Item sold', body: 'Paid.', url: null } },
  )
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(['node', 'drain-outbox.ts', `--dir=${outboxDir}`], { DATABASE_FILE: databaseFile }, logger)

  const [written] = await readdir(outboxDir)

  assert.deepEqual(stream.story(), [
    'notification.deliver will',
    'notification.deliver doing',
    'notification.deliver did',
  ])
  assert.equal(stream.data('notification.deliver', 'doing').file, path.join(outboxDir, written ?? ''))
  assert.match(written ?? '', /^obx_[0-9A-HJKMNP-TV-Z]{26}\.eml$/)
  assert.equal(stream.data('notification.deliver', 'did').count, 1)
  assert.match(String(stream.line('notification.deliver', 'will').msg), /^🎬 /)
})

test('a drained message never puts the recipient address in the log', async (t) => {
  const databaseFile = await temporaryDatabase(t)
  const outboxDir = path.join(path.dirname(databaseFile), 'outbox')

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await enqueueOutboxMessage(
    { db: setupDb, clock: systemClock },
    { recipient: 'artist@example.com', message: { subject: 'Item sold', body: 'Paid.', url: null } },
  )
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(['node', 'drain-outbox.ts', `--dir=${outboxDir}`], { DATABASE_FILE: databaseFile }, logger)

  assert.equal(stream.text().includes('artist@example.com'), false)
})

test('main logs a zero-count summary when there is nothing to send', async (t) => {
  const databaseFile = await temporaryDatabase(t)

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(['node', 'drain-outbox.ts'], {
    DATABASE_FILE: databaseFile,
    OUTBOX_DIR: path.join(path.dirname(databaseFile), 'outbox'),
  }, logger)

  assert.deepEqual(stream.story(), ['notification.deliver will', 'notification.deliver did'])
  assert.equal(stream.data('notification.deliver', 'did').count, 0)
})

test('main logs the error and sets a failing exit code when the drain itself fails', async (t) => {
  const databaseFile = await temporaryDatabase(t)
  // No migrations applied, so the query inside `drainOutbox` fails against a
  // database with no tables — the drain itself failing, not a usage mistake.

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })
  const exitCodeBefore = process.exitCode
  t.after(() => {
    process.exitCode = exitCodeBefore
  })

  await main(['node', 'drain-outbox.ts'], { DATABASE_FILE: databaseFile }, logger)

  assert.equal(process.exitCode, 1)
  const failed = stream.line('notification.deliver', 'failed')
  assert.equal(failed.level, 'error')
  assert.equal(typeof failed.duration_ms, 'number')
  assert.deepEqual(Object.keys(failed.error as object).sort(), ['message', 'type'])
  assert.match(String(failed.msg), /^❌ /)
})

test('a flag the command does not take is a mistake, not silence', async (t) => {
  const databaseFile = await temporaryDatabase(t)

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()

  await assert.rejects(() =>
    main(['node', 'drain-outbox.ts', '--evrything'], { DATABASE_FILE: databaseFile }),
  )
})

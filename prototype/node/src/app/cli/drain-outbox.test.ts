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
  const logger = createCliLogger({ logLevel: 'info' }, { stream })

  await main(['node', 'drain-outbox.ts', `--dir=${outboxDir}`], { DATABASE_FILE: databaseFile }, logger)

  const [written] = await readdir(outboxDir)

  const lines = stream.lines()
  const drainedLine = lines.find((line) => line.event === 'outbox.drained')
  assert.equal(drainedLine?.recipient, 'artist@example.com')
  assert.equal(drainedLine?.file, path.join(outboxDir, written ?? ''))
  assert.match(written ?? '', /^obx_[0-9A-HJKMNP-TV-Z]{26}\.eml$/)

  const summaryLine = lines.find((line) => line.event === 'outbox.drain_run')
  assert.equal(summaryLine?.count, 1)
})

test('main logs a zero-count summary when there is nothing to send', async (t) => {
  const databaseFile = await temporaryDatabase(t)

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info' }, { stream })

  await main(['node', 'drain-outbox.ts'], {
    DATABASE_FILE: databaseFile,
    OUTBOX_DIR: path.join(path.dirname(databaseFile), 'outbox'),
  }, logger)

  const summaryLine = stream.lines().find((line) => line.event === 'outbox.drain_run')
  assert.equal(summaryLine?.count, 0)
})

test('main logs the error and sets a failing exit code when the drain itself fails', async (t) => {
  const databaseFile = await temporaryDatabase(t)
  // No migrations applied, so the query inside `drainOutbox` fails against a
  // database with no tables — the drain itself failing, not a usage mistake.

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info' }, { stream })
  const exitCodeBefore = process.exitCode
  t.after(() => {
    process.exitCode = exitCodeBefore
  })

  await main(['node', 'drain-outbox.ts'], { DATABASE_FILE: databaseFile }, logger)

  assert.equal(process.exitCode, 1)
  assert.equal(stream.lines().some((line) => line.err !== undefined), true)
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

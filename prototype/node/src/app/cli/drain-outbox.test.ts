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

type Recorder = { lines: string[] }

function recordConsole(t: { after(fn: () => unknown): void }): Recorder {
  const lines: string[] = []
  const originalLog = console.log
  console.log = (line: unknown) => {
    lines.push(String(line))
  }
  t.after(() => {
    console.log = originalLog
  })

  return { lines }
}

async function temporaryDatabase(t: { after(fn: () => unknown): void }): Promise<string> {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-drain-outbox-'))
  t.after(() => rm(dir, { recursive: true, force: true }))

  return path.join(dir, 'test.sqlite3')
}

test('main writes every pending message and says where they landed', async (t) => {
  const databaseFile = await temporaryDatabase(t)
  const outboxDir = path.join(path.dirname(databaseFile), 'outbox')

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await enqueueOutboxMessage(
    { db: setupDb, clock: systemClock },
    { recipient: 'artist@example.com', message: { subject: 'Item sold', body: 'Paid.', url: null } },
  )
  await setupDb.destroy()

  const recorder = recordConsole(t)

  await main(['node', 'drain-outbox.ts', `--dir=${outboxDir}`], { DATABASE_FILE: databaseFile })

  assert.deepEqual(await readdir(outboxDir), ['1.eml'])
  assert.equal(recorder.lines[0], `${path.join(outboxDir, '1.eml')} artist@example.com Item sold`)
  assert.equal(recorder.lines.at(-1), `1 message(s) written to ${outboxDir}.`)
})

test('main says so when there is nothing to send', async (t) => {
  const databaseFile = await temporaryDatabase(t)

  const setupDb = openDatabase(databaseFile)
  await migrateToLatest(setupDb)
  await setupDb.destroy()

  const recorder = recordConsole(t)

  await main(['node', 'drain-outbox.ts'], {
    DATABASE_FILE: databaseFile,
    OUTBOX_DIR: path.join(path.dirname(databaseFile), 'outbox'),
  })

  assert.equal(recorder.lines.at(-1), 'The outbox is empty.')
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

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { sql } from 'kysely'
import { main } from './prepare-db.ts'
import { openDatabase } from '../db/database.ts'
import { createCliLogger } from '../logging.ts'
import { captureLogLines } from '../test/log-lines.ts'

test('main migrates then seeds a fresh database in one call', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-prepare-db-'))
  t.after(() => rm(dir, { recursive: true, force: true }))
  const databaseFile = path.join(dir, 'db.sqlite3')

  const logger = createCliLogger({ logLevel: 'silent', environment: 'test' })

  await main(['node', 'prepare-db.ts'], { DATABASE_FILE: databaseFile }, logger)

  const db = openDatabase(databaseFile)
  t.after(() => db.destroy())

  const migrations = await sql<{ name: string }>`select name from kysely_migration`.execute(db)
  assert.ok(migrations.rows.length > 0)

  // 4 demo sellers plus the 2 wizarding sellers `seedWizardingSellers` adds.
  const sellers = await db.selectFrom('sellers').selectAll().execute()
  assert.equal(sellers.length, 6)
})

test('migrate.run and seed.run are root stories, and migrate.apply rides along under one', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-prepare-db-'))
  t.after(() => rm(dir, { recursive: true, force: true }))
  const databaseFile = path.join(dir, 'db.sqlite3')

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  await main(['node', 'prepare-db.ts'], { DATABASE_FILE: databaseFile }, logger)

  assert.match(String(stream.line('migrate.run', 'will').msg), /^🎬 /)
  assert.match(String(stream.line('migrate.apply', 'did').msg), /^🟢 /)
  assert.match(String(stream.line('seed.run', 'will').msg), /^🎬 /)
})

test('main skips seeding when the migrate step fails', async (t) => {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-prepare-db-'))
  t.after(() => rm(dir, { recursive: true, force: true }))
  // A directory in place of the database file: migrate's `openDatabase` call
  // fails to open it, so the seed half never runs.
  const databaseFile = dir

  const stream = captureLogLines()
  const logger = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })
  const exitCodeBefore = process.exitCode
  t.after(() => {
    process.exitCode = exitCodeBefore
  })

  await main(['node', 'prepare-db.ts'], { DATABASE_FILE: databaseFile }, logger)

  assert.equal(process.exitCode, 1)
  assert.equal(stream.line('migrate.run', 'failed').level, 'error')
  // The seed half writes a `seed.run` story when it runs; its absence is the
  // skip.
  assert.equal(stream.linesFor('seed.run').length, 0)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sql } from 'kysely'
import { IN_MEMORY_DATABASE, openDatabase } from './database.ts'
import { migrateToLatest } from './migrator.ts'

test('migrating a new database applies every migration', async () => {
  const db = openDatabase(IN_MEMORY_DATABASE)

  const applied = await migrateToLatest(db)

  assert.ok(applied.length > 0)
  assert.deepEqual(
    applied.map((migration) => migration.status),
    applied.map(() => 'Success'),
  )
  await db.destroy()
})

test('migrating an up-to-date database applies nothing', async () => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const applied = await migrateToLatest(db)

  assert.deepEqual(applied, [])
  await db.destroy()
})

test('every applied migration is recorded so a rerun can skip it', async () => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  const applied = await migrateToLatest(db)

  const recorded = await sql<{ name: string }>`select name from kysely_migration`.execute(db)

  assert.deepEqual(
    recorded.rows.map((row) => row.name).sort(),
    applied.map((migration) => migration.migrationName).sort(),
  )
  await db.destroy()
})

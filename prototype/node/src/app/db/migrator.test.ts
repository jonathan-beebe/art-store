import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sql } from 'kysely'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from './database.ts'
import { migrateDown, migrateToLatest, pendingMigrations } from './migrator.ts'

test('migrating a new database applies every migration', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())

  const applied = await migrateToLatest(db)

  assert.ok(applied.length > 0)
  assert.deepEqual(
    applied.map((migration) => migration.status),
    applied.map(() => 'Success'),
  )
})

test('migrating an up-to-date database applies nothing', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())
  await migrateToLatest(db)

  const applied = await migrateToLatest(db)

  assert.deepEqual(applied, [])
})

test('every applied migration is recorded so a rerun can skip it', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())
  const applied = await migrateToLatest(db)

  const recorded = await sql<{ name: string }>`select name from kysely_migration`.execute(db)

  assert.deepEqual(
    recorded.rows.map((row) => row.name).sort(),
    applied.map((migration) => migration.migrationName).sort(),
  )
})

test('a fresh database has every migration pending', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())

  const pending = await pendingMigrations(db)

  assert.ok(pending.length > 0)
  assert.ok(pending.every((migration) => migration.executedAt === undefined))
})

test('an up-to-date database has nothing pending', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())
  await migrateToLatest(db)

  const pending = await pendingMigrations(db)

  assert.deepEqual(pending, [])
})

test('migrating down to zero and back up leaves the same tables behind', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())
  const up = await migrateToLatest(db)
  const tablesAfterUp = await userTables(db)

  const down = await migrateDown(db)

  assert.deepEqual(
    down.map((migration) => migration.status),
    down.map(() => 'Success'),
  )
  assert.deepEqual(down.map((migration) => migration.migrationName).sort(), up.map((migration) => migration.migrationName).sort())
  assert.deepEqual(await userTables(db), [])

  const upAgain = await migrateToLatest(db)

  assert.deepEqual(
    upAgain.map((migration) => migration.migrationName).sort(),
    up.map((migration) => migration.migrationName).sort(),
  )
  assert.deepEqual(await userTables(db), tablesAfterUp)
})

/** Table names the migrations own, excluding Kysely's own bookkeeping tables. */
async function userTables(db: AppDatabase): Promise<readonly string[]> {
  const { rows } = await sql<{
    name: string
  }>`select name from sqlite_master where type = 'table' and name not like 'sqlite_%' and name not like 'kysely_%' order by name`.execute(
    db,
  )
  return rows.map((row) => row.name)
}

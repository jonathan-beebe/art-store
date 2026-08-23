import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, writeFile } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { sql } from 'kysely'
import { IN_MEMORY_DATABASE, openDatabase, removeDatabaseFile } from './database.ts'

test('an opened database refuses a row pointing at a missing parent', async () => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await sql`create table sellers (id integer primary key)`.execute(db)
  await sql`create table listings (id integer primary key, seller_id integer references sellers(id))`.execute(db)

  await assert.rejects(
    () => sql`insert into listings (seller_id) values (404)`.execute(db),
    /FOREIGN KEY/,
  )
  await db.destroy()
})

test('an opened database reads snake_case columns as camelCase', async () => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await sql`create table listings (price_cents integer)`.execute(db)
  await sql`insert into listings (price_cents) values (1250)`.execute(db)

  const rows = await sql<{ priceCents: number }>`select price_cents from listings`.execute(db)

  assert.equal(rows.rows[0]?.priceCents, 1250)
  await db.destroy()
})

test('a database file and its write-ahead log are removed together', async () => {
  const directory = await mkdtemp(path.join(tmpdir(), 'art-store-'))
  const file = path.join(directory, 'development.sqlite3')
  await Promise.all(
    [file, `${file}-shm`, `${file}-wal`].map((each) => writeFile(each, '')),
  )

  await removeDatabaseFile(file)

  assert.equal(existsSync(file), false)
  assert.equal(existsSync(`${file}-shm`), false)
  assert.equal(existsSync(`${file}-wal`), false)
})

test('removing a database that does not exist succeeds', async () => {
  const directory = await mkdtemp(path.join(tmpdir(), 'art-store-'))

  await removeDatabaseFile(path.join(directory, 'absent.sqlite3'))
})

test('an in-memory database has no file to remove', async () => {
  await removeDatabaseFile(IN_MEMORY_DATABASE)
})

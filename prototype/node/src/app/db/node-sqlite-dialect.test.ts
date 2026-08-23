import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { Kysely, sql } from 'kysely'
import type { Generated } from 'kysely'
import { NodeSqliteDialect } from './node-sqlite-dialect.ts'

type TestDatabase = {
  things: {
    id: Generated<number>
    name: string
  }
}

function openTestDatabase(
  file = ':memory:',
  recordSql: (statement: string) => void = () => {},
): Kysely<TestDatabase> {
  return new Kysely<TestDatabase>({
    dialect: new NodeSqliteDialect(file),
    log: (event) => recordSql(event.query.sql),
  })
}

async function createThingsTable(db: Kysely<TestDatabase>): Promise<void> {
  await sql`create table things (id integer primary key autoincrement, name text not null)`
    .execute(db)
}

async function temporaryDatabaseFile(): Promise<string> {
  const directory = await mkdtemp(path.join(tmpdir(), 'art-store-dialect-'))

  return path.join(directory, 'test.sqlite3')
}

test('a select reads the rows it selects', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)
  await db.insertInto('things').values({ name: 'kettle' }).execute()

  const things = await db.selectFrom('things').selectAll().execute()

  assert.deepEqual(things, [{ id: 1, name: 'kettle' }])
  await db.destroy()
})

test('a stream yields every row the query selects', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)
  await db.insertInto('things').values([{ name: 'kettle' }, { name: 'teapot' }]).execute()

  const names: string[] = []
  for await (const thing of db.selectFrom('things').selectAll().stream()) {
    names.push(thing.name)
  }

  assert.deepEqual(names, ['kettle', 'teapot'])
  await db.destroy()
})

test('an insert reports the rows it wrote and the id it assigned', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)

  const result = await db
    .insertInto('things')
    .values({ name: 'kettle' })
    .executeTakeFirstOrThrow()

  assert.equal(result.numInsertedOrUpdatedRows, 1n)
  assert.equal(result.insertId, 1n)
  await db.destroy()
})

test('an insert with returning reads back the row it wrote', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)

  const thing = await db
    .insertInto('things')
    .values({ name: 'kettle' })
    .returningAll()
    .executeTakeFirstOrThrow()

  assert.deepEqual(thing, { id: 1, name: 'kettle' })
  await db.destroy()
})

test('a transaction that returns keeps its writes', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)

  await db.transaction().execute(async (trx) => {
    await trx.insertInto('things').values({ name: 'kettle' }).execute()
  })

  const things = await db.selectFrom('things').selectAll().execute()

  assert.equal(things.length, 1)
  await db.destroy()
})

test('a transaction that throws keeps none of its writes', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)

  await assert.rejects(
    () =>
      db.transaction().execute(async (trx) => {
        await trx.insertInto('things').values({ name: 'kettle' }).execute()
        throw new Error('abandoned')
      }),
    /abandoned/,
  )

  const things = await db.selectFrom('things').selectAll().execute()

  assert.deepEqual(things, [])
  await db.destroy()
})

test('a transaction begins immediate', async () => {
  const statements: string[] = []
  const db = openTestDatabase(':memory:', (statement) => statements.push(statement))
  await createThingsTable(db)

  await db.transaction().execute(async (trx) => {
    await trx.insertInto('things').values({ name: 'kettle' }).execute()
  })

  assert.deepEqual(
    statements.filter((statement) => statement.startsWith('begin')),
    ['begin immediate'],
  )
  await db.destroy()
})

test('an open transaction holds the write lock before it reads anything', async () => {
  const file = await temporaryDatabaseFile()
  const db = openTestDatabase(file)
  await createThingsTable(db)

  // A second connection with no busy timeout fails at once when the lock is held.
  const other = new DatabaseSync(file)
  let refusal: unknown

  await db.transaction().execute(async () => {
    try {
      other.prepare('insert into things (name) values (?)').run('teapot')
    } catch (error) {
      refusal = error
    }
  })

  other.close()
  await db.destroy()

  assert.match(String(refusal), /database is locked/)
})

test('an opened connection enforces foreign keys and waits on a locked database', async () => {
  const db = openTestDatabase()

  const foreignKeys = await sql<{ foreign_keys: number }>`pragma foreign_keys`.execute(db)
  const busyTimeout = await sql<{ timeout: number }>`pragma busy_timeout`.execute(db)

  assert.equal(foreignKeys.rows[0]?.foreign_keys, 1)
  assert.equal(busyTimeout.rows[0]?.timeout, 5000)
  await db.destroy()
})

test('a parameter SQLite cannot bind names its own type', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)

  await assert.rejects(
    () => sql`insert into things (name) values (${true})`.execute(db),
    /cannot bind a boolean parameter/,
  )
  await db.destroy()
})

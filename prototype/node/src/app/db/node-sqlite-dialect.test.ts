import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { Kysely, sql } from 'kysely'
import type { Generated } from 'kysely'
import { NodeSqliteDialect, STATEMENT_CACHE_LIMIT } from './node-sqlite-dialect.ts'

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

test('an opened connection sets synchronous to NORMAL under WAL', async () => {
  // :memory: databases do not carry the WAL migration this pairs with, so a
  // temporary file is what makes the assertion meaningful.
  const file = await temporaryDatabaseFile()
  const db = openTestDatabase(file)

  const synchronous = await sql<{ synchronous: number }>`pragma synchronous`.execute(db)

  assert.equal(synchronous.rows[0]?.synchronous, 1)
  await db.destroy()
})

test('executing the same insert text repeatedly writes distinct rows and reports correct results each time', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)

  const first = await db.insertInto('things').values({ name: 'kettle' }).executeTakeFirstOrThrow()
  const second = await db.insertInto('things').values({ name: 'teapot' }).executeTakeFirstOrThrow()

  assert.equal(first.insertId, 1n)
  assert.equal(first.numInsertedOrUpdatedRows, 1n)
  assert.equal(second.insertId, 2n)
  assert.equal(second.numInsertedOrUpdatedRows, 1n)

  const things = await db.selectFrom('things').selectAll().execute()
  assert.deepEqual(things, [
    { id: 1, name: 'kettle' },
    { id: 2, name: 'teapot' },
  ])
  await db.destroy()
})

test('a query re-executed after a schema change still works', async () => {
  const db = openTestDatabase()
  await createThingsTable(db)

  await sql`insert into things (name) values (${'kettle'})`.execute(db)
  await sql`alter table things add column note text`.execute(db)
  await sql`insert into things (name) values (${'teapot'})`.execute(db)

  const things = await sql<{ id: number, name: string, note: string | null }>`select * from things`
    .execute(db)

  assert.deepEqual(things.rows, [
    { id: 1, name: 'kettle', note: null },
    { id: 2, name: 'teapot', note: null },
  ])
  await db.destroy()
})

test('a statement evicted from the cache still executes correctly when its text runs again', async () => {
  const db = openTestDatabase()
  const first = await sql<{ n: number }>`select 0 as n`.execute(db)

  for (let value = 1; value <= STATEMENT_CACHE_LIMIT; value += 1) {
    await sql.raw(`select ${value} as n`).execute(db)
  }

  const repeated = await sql<{ n: number }>`select 0 as n`.execute(db)

  assert.deepEqual(first.rows, [{ n: 0 }])
  assert.deepEqual(repeated.rows, [{ n: 0 }])
  await db.destroy()
})

test('two connections never share prepared statements', async () => {
  const dbA = openTestDatabase()
  const dbB = openTestDatabase()
  await createThingsTable(dbA)
  await createThingsTable(dbB)

  await dbA.insertInto('things').values({ name: 'kettle' }).execute()
  await dbB.insertInto('things').values({ name: 'teapot' }).execute()

  const thingsA = await dbA.selectFrom('things').selectAll().execute()
  const thingsB = await dbB.selectFrom('things').selectAll().execute()

  assert.deepEqual(thingsA, [{ id: 1, name: 'kettle' }])
  assert.deepEqual(thingsB, [{ id: 1, name: 'teapot' }])
  await dbA.destroy()
  await dbB.destroy()
})

test('a new connection on the same file works after the previous one is destroyed', async () => {
  const file = await temporaryDatabaseFile()
  const first = openTestDatabase(file)
  await createThingsTable(first)
  await first.insertInto('things').values({ name: 'kettle' }).execute()
  await first.destroy()

  const second = openTestDatabase(file)
  await second.insertInto('things').values({ name: 'teapot' }).execute()
  const things = await second.selectFrom('things').selectAll().execute()

  assert.deepEqual(things, [
    { id: 1, name: 'kettle' },
    { id: 2, name: 'teapot' },
  ])
  await second.destroy()
})

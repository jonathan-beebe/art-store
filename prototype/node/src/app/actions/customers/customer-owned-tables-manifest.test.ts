import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sql } from 'kysely'
import { IN_MEMORY_DATABASE, openDatabase } from '../../db/database.ts'
import { migrateToLatest } from '../../db/migrator.ts'
import {
  FOLDED_CUSTOMER_TABLES,
  LEFT_BEHIND_CUSTOMER_TABLES,
  REPOINTED_CUSTOMER_TABLES,
} from './repointed-customer-tables.ts'

function snakeToCamel(name: string): string {
  return name.replace(/_([a-z0-9])/g, (_match, char: string) => char.toUpperCase())
}

/** Every table the migrations create that holds a `customer_id` column, read from the schema itself. */
async function tablesWithCustomerId(): Promise<readonly string[]> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const { rows: tables } = await sql<{
    name: string
  }>`select name from sqlite_master where type = 'table' and name not like 'sqlite_%' and name not like 'kysely_%'`.execute(
    db,
  )

  const owners: string[] = []
  for (const table of tables) {
    const { rows: columns } = await sql<{
      name: string
    }>`select name from pragma_table_info(${table.name})`.execute(db)

    if (columns.some((column) => column.name === 'customer_id')) {
      owners.push(snakeToCamel(table.name))
    }
  }

  await db.destroy()

  return owners.sort()
}

test('every column named customer_id is repointed, folded, or an explicit, named left-behind', async () => {
  const manifested = [
    ...REPOINTED_CUSTOMER_TABLES,
    ...Object.keys(FOLDED_CUSTOMER_TABLES),
    ...Object.keys(LEFT_BEHIND_CUSTOMER_TABLES),
  ].sort()

  assert.deepEqual(
    await tablesWithCustomerId(),
    manifested,
    'a table with a customer_id column that is not in any of the three lists is unclassified — decide where it belongs',
  )
})

test('no table is claimed by more than one of the three lists', () => {
  const repointed = new Set(REPOINTED_CUSTOMER_TABLES)
  const folded = new Set(Object.keys(FOLDED_CUSTOMER_TABLES))
  const leftBehind = new Set(Object.keys(LEFT_BEHIND_CUSTOMER_TABLES))

  for (const table of repointed) {
    assert.equal(folded.has(table), false, `${table} is both repointed and folded`)
    assert.equal(leftBehind.has(table), false, `${table} is both repointed and left behind`)
  }
  for (const table of folded) {
    assert.equal(leftBehind.has(table), false, `${table} is both folded and left behind`)
  }
})

test('every left-behind table names a non-empty reason', () => {
  for (const [table, reason] of Object.entries(LEFT_BEHIND_CUSTOMER_TABLES)) {
    assert.ok(reason.trim().length > 0, `${table} has no reason`)
  }
})

test('every folded table names a non-empty reason', () => {
  for (const [table, reason] of Object.entries(FOLDED_CUSTOMER_TABLES)) {
    assert.ok(reason.trim().length > 0, `${table} has no reason`)
  }
})

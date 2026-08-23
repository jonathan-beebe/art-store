import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sql } from 'kysely'
import { buildTestApp } from '../../test/build-test-app.ts'
import { readMergedTableColumns, hasColumns } from './merged-table-columns.ts'

test('a table the schema does not have is absent from the result', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)

  const schema = await readMergedTableColumns(db)

  assert.equal(hasColumns(schema, 'conversations', 'customer_id'), false)
})

test('once created, the probe reports it with its columns', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)
  await sql`create table conversations (id integer primary key, customer_id integer, kind text)`.execute(db)

  const schema = await readMergedTableColumns(db)

  assert.equal(hasColumns(schema, 'conversations', 'customer_id'), true)
  assert.equal(hasColumns(schema, 'conversations', 'kind'), true)
})

test('hasColumns is false for a column the table lacks', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)
  await sql`create table conversations (id integer primary key, customer_id integer, kind text)`.execute(db)

  const schema = await readMergedTableColumns(db)

  assert.equal(hasColumns(schema, 'conversations', 'seller_id'), false)
})

test('hasColumns is true only when every named column is present', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)
  await sql`create table conversations (id integer primary key, customer_id integer, kind text)`.execute(db)

  const schema = await readMergedTableColumns(db)

  assert.equal(hasColumns(schema, 'conversations', 'customer_id', 'kind'), true)
  assert.equal(hasColumns(schema, 'conversations', 'customer_id', 'seller_id'), false)
})

test('a table nobody asked about never appears in the result', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)
  await sql`create table bystanders (id integer primary key)`.execute(db)

  const schema = await readMergedTableColumns(db)

  assert.equal(schema.has('bystanders'), false)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { buildTestApp } from '../../test/build-test-app.ts'
import { fixtureId } from '../../test/fixture-ids.ts'
import { fixedClock } from '../../clock.ts'
import { openDatabase } from '../../db/database.ts'
import { migrateToLatest } from '../../db/migrator.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'
import { resolveCurrentCustomer } from './resolve-current-customer.ts'

test('a request with no cookie gets a new anonymous customer', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const customer = await resolveCurrentCustomer({ db, clock }, null)

  assert.equal(customer.email, null)
  const rows = await db.selectFrom('customers').selectAll().execute()
  assert.equal(rows.length, 1)
})

test('a request whose cookie names a customer gets that customer and creates nobody new', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const existing = await createAnonymousCustomer({ db, clock })

  const customer = await resolveCurrentCustomer({ db, clock }, existing.id)

  assert.equal(customer.id, existing.id)
  const rows = await db.selectFrom('customers').selectAll().execute()
  assert.equal(rows.length, 1)
})

test('a cookie naming a row that is gone gets a fresh anonymous customer', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const customer = await resolveCurrentCustomer({ db, clock }, fixtureId('cus', 999999))

  assert.notEqual(customer.id, 999999)
  const rows = await db.selectFrom('customers').selectAll().execute()
  assert.equal(rows.length, 1)
})

test('a remembered customer resolves while another connection holds the write lock', async (t) => {
  const directory = await mkdtemp(path.join(tmpdir(), 'resolve-current-customer-'))
  const file = path.join(directory, 'db.sqlite')
  const db = openDatabase(file)
  await migrateToLatest(db)
  const clock = fixedClock(new Date('2026-08-24T12:00:00.000Z'))

  const existing = await createAnonymousCustomer({ db, clock })

  const rival = new DatabaseSync(file)
  rival.exec('begin immediate')

  t.after(async () => {
    rival.exec('rollback')
    rival.close()
    await db.destroy()
    await rm(directory, { recursive: true, force: true })
  })

  const customer = await resolveCurrentCustomer({ db, clock }, existing.id)

  assert.equal(customer.id, existing.id)
})

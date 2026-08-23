import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../../test/build-test-app.ts'
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

  const customer = await resolveCurrentCustomer({ db, clock }, String(existing.id))

  assert.equal(customer.id, existing.id)
  const rows = await db.selectFrom('customers').selectAll().execute()
  assert.equal(rows.length, 1)
})

test('a cookie naming a row that is gone gets a fresh anonymous customer', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const customer = await resolveCurrentCustomer({ db, clock }, '999999')

  assert.notEqual(customer.id, 999999)
  const rows = await db.selectFrom('customers').selectAll().execute()
  assert.equal(rows.length, 1)
})

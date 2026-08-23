import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../../test/build-test-app.ts'
import { findAdminByEmail } from './find-admin-by-email.ts'
import { seedAdmins } from '../../db/seed-admins.ts'

test('it finds a seeded operator by address', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  await seedAdmins({ db, clock })

  const admin = await findAdminByEmail({ db }, 'jonathan-beebe@outlook.com')

  assert.equal(admin?.name, 'Jonathan Beebe')
})

test('an address differing only in case reaches the same admin', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  await seedAdmins({ db, clock })

  const admin = await findAdminByEmail({ db }, 'Jonathan-Beebe@Outlook.com')

  assert.equal(admin?.email, 'jonathan-beebe@outlook.com')
})

test('an address nobody seeded resolves to null', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  await seedAdmins({ db, clock })

  const admin = await findAdminByEmail({ db }, 'nobody@example.com')

  assert.equal(admin, null)
})

test('it returns null rather than throwing for a blank address', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)

  const admin = await findAdminByEmail({ db }, '')

  assert.equal(admin, null)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, TEST_INSTANT } from '../test/build-test-app.ts'
import { seedAdmins, SEEDED_ADMINS } from './seed-admins.ts'

test('it seeds the two operators from the brief', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  await seedAdmins({ db, clock })

  const rows = await db.selectFrom('admins').selectAll().orderBy('email').execute()
  assert.deepEqual(
    rows.map((row) => ({ email: row.email, name: row.name })),
    [
      { email: 'annaschmunk@pm.me', name: 'Anna Schmunk' },
      { email: 'jonathan-beebe@outlook.com', name: 'Jonathan Beebe' },
    ],
  )
})

test('seeding a second time adds nobody and leaves two rows', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  await seedAdmins({ db, clock })

  const added = await seedAdmins({ db, clock })

  assert.equal(added, 0)
  const rows = await db.selectFrom('admins').selectAll().execute()
  assert.equal(rows.length, 2)
})

test("it stamps createdAt at the clock's now", async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  await seedAdmins({ db, clock })

  const rows = await db.selectFrom('admins').selectAll().execute()
  for (const row of rows) {
    assert.equal(row.createdAt, TEST_INSTANT.toISOString())
  }
})

test('SEEDED_ADMINS holds exactly two entries', () => {
  assert.equal(SEEDED_ADMINS.length, 2)
})

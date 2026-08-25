import { test, type TestContext } from 'node:test'
import assert from 'node:assert/strict'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from './database.ts'
import { migrateToLatest } from './migrator.ts'
import { seedAdmins } from './seed-admins.ts'
import { seedDemoData } from './seed-demo-data.ts'
import { seedWizardingSellers, WIZARDING_SELLERS } from './seed-wizarding-sellers.ts'

const NOW = new Date('2026-08-24T12:00:00.000Z')
const clock = { now: () => NOW }

/** A migrated `:memory:` database seeded the way `npm run seed` seeds one:
 * admins, demo data, then the wizarding sellers. */
async function withSeededDatabase(t: TestContext): Promise<AppDatabase> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())
  await migrateToLatest(db)
  await seedAdmins({ db, clock })
  await seedDemoData({ db, clock })

  return db
}

test('it seeds two verified sellers with a shop name and a live catalog', async (t) => {
  const db = await withSeededDatabase(t)

  const summary = await seedWizardingSellers(db)
  assert.deepEqual(summary, { sellerCount: 2, listingCount: 8 })

  const emails = WIZARDING_SELLERS.map((seller) => seller.email)
  const sellers = await db.selectFrom('sellers').selectAll().where('email', 'in', emails).execute()

  assert.equal(sellers.length, 2)
  assert.ok(sellers.every((seller) => seller.emailVerifiedAt !== null))
  assert.ok(sellers.every((seller) => seller.shopName !== null))

  const listings = await db
    .selectFrom('listings')
    .select(['status', 'medium'])
    .where(
      'sellerId',
      'in',
      sellers.map((seller) => seller.id),
    )
    .execute()

  assert.equal(listings.length, 8)
  assert.ok(listings.every((listing) => listing.status === 'for_sale'))
  assert.deepEqual(
    new Set(listings.map((listing) => listing.medium)),
    new Set(['plant', 'publication', 'jewelry', 'curio']),
  )
})

test('it seeds onto a database the demo seed already refuses to touch', async (t) => {
  const db = await withSeededDatabase(t)

  const demoAgain = await seedDemoData({ db, clock })
  assert.equal(demoAgain, null)

  const summary = await seedWizardingSellers(db)
  assert.deepEqual(summary, { sellerCount: 2, listingCount: 8 })
})

test('running it a second time skips and touches nothing', async (t) => {
  const db = await withSeededDatabase(t)

  await seedWizardingSellers(db)
  const again = await seedWizardingSellers(db)

  assert.equal(again, null)

  const sellers = await db.selectFrom('sellers').select('id').execute()
  const listings = await db.selectFrom('listings').select('id').execute()
  assert.equal(sellers.length, 6)
  assert.equal(listings.length, 37)
})

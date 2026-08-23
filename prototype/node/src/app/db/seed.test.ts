import { test, type TestContext } from 'node:test'
import assert from 'node:assert/strict'
import { REMOVED_LISTING_TITLE } from './seed-catalog.ts'
import { CASEY_EMAIL } from './seed-customers.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from './database.ts'
import { seedDemoData, type SeedDemoDataSummary } from './seed-demo-data.ts'
import { seedAdmins } from './seed-admins.ts'
import { migrateToLatest } from './migrator.ts'

const NOW = new Date('2026-08-24T12:00:00.000Z')
const clock = { now: () => NOW }

/** A migrated `:memory:` database seeded the way `npm run seed` seeds one. */
async function withSeededDatabase(t: TestContext): Promise<AppDatabase> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())
  await migrateToLatest(db)
  await seedAdmins({ db, clock })
  await seedDemoData({ db, clock })

  return db
}

function countBy<Row>(rows: readonly Row[], key: (row: Row) => string): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const row of rows) {
    const value = key(row)
    counts[value] = (counts[value] ?? 0) + 1
  }
  return counts
}

test('it seeds four verified sellers with a shop name', async (t) => {
  const db = await withSeededDatabase(t)

  const sellers = await db.selectFrom('sellers').selectAll().execute()

  assert.equal(sellers.length, 4)
  assert.ok(sellers.every((seller) => seller.emailVerifiedAt !== null))
  assert.ok(sellers.every((seller) => seller.shopName !== null))
})

test('it seeds listings across media, mostly for_sale with some draft and sold', async (t) => {
  const db = await withSeededDatabase(t)

  const listings = await db.selectFrom('listings').select(['status', 'medium']).execute()
  const byStatus = countBy(listings, (listing) => listing.status)

  assert.equal(listings.length, 29)
  assert.equal(byStatus.for_sale, 24)
  assert.equal(byStatus.draft, 3)
  assert.equal(byStatus.sold, 2)
  assert.equal(new Set(listings.map((listing) => listing.medium)).size, 6)
})

test('one for_sale listing carries an active temporary removal', async (t) => {
  const db = await withSeededDatabase(t)

  const removed = await db
    .selectFrom('listingRemovals')
    .innerJoin('listings', 'listings.id', 'listingRemovals.listingId')
    .select(['listings.title', 'listings.status', 'listingRemovals.kind', 'listingRemovals.liftedAt'])
    .execute()

  assert.deepEqual(removed, [{ title: REMOVED_LISTING_TITLE, status: 'for_sale', kind: 'temporary', liftedAt: null }])
})

test('casey is verified with favorites, view history, and a cart', async (t) => {
  const db = await withSeededDatabase(t)

  const casey = await db.selectFrom('customers').selectAll().where('email', '=', CASEY_EMAIL).executeTakeFirstOrThrow()
  assert.ok(casey.emailVerifiedAt !== null)

  const favorites = await db.selectFrom('favorites').select('id').where('customerId', '=', casey.id).execute()
  assert.equal(favorites.length, 3)

  // One standing cart casey is still shopping in, plus one spent cart per
  // order placed against it (`placeOrder` empties a cart, never deletes it).
  const carts = await db.selectFrom('carts').select('id').where('customerId', '=', casey.id).execute()
  assert.equal(carts.length, 4)

  const cartItems = await db
    .selectFrom('cartItems')
    .innerJoin('carts', 'carts.id', 'cartItems.cartId')
    .select('cartItems.id')
    .where('carts.customerId', '=', casey.id)
    .execute()
  assert.equal(cartItems.length, 2)
})

test('it seeds one blocked customer', async (t) => {
  const db = await withSeededDatabase(t)

  const blocks = await db.selectFrom('customerBlocks').selectAll().execute()

  assert.equal(blocks.length, 1)
  assert.equal(blocks[0]?.liftedAt, null)
})

test('it seeds a few anonymous customers', async (t) => {
  const db = await withSeededDatabase(t)

  const anonymous = await db.selectFrom('customers').selectAll().where('email', 'is', null).execute()

  assert.equal(anonymous.length, 3)
  assert.ok(anonymous.every((customer) => customer.emailVerifiedAt === null))
})

test('listing events cover casey, the order history, and the anonymous browsers', async (t) => {
  const db = await withSeededDatabase(t)

  const events = await db.selectFrom('listingEvents').select('eventType').execute()
  const byType = countBy(events, (event) => event.eventType)

  assert.equal(byType.view, 14)
  assert.equal(byType.favorite, 3)
  assert.equal(byType.cart_add, 5)
})

test('order history reaches paid, shipped, and delivered across two sellers', async (t) => {
  const db = await withSeededDatabase(t)

  const orders = await db.selectFrom('orders').select('status').execute()
  const fulfillments = await db.selectFrom('fulfillments').select(['status', 'sellerId']).execute()
  const payments = await db.selectFrom('payments').select('status').execute()

  assert.deepEqual(
    orders.map((order) => order.status).sort(),
    ['delivered', 'paid', 'shipped'],
  )
  assert.deepEqual(
    fulfillments.map((fulfillment) => fulfillment.status).sort(),
    ['awaiting_shipment', 'delivered', 'shipped'],
  )
  assert.equal(new Set(fulfillments.map((fulfillment) => fulfillment.sellerId)).size, 2)
  assert.equal(payments.length, 3)
  assert.ok(payments.every((payment) => payment.status === 'approved'))
})

test('escrow holds three, releases one, and pays the delivered order out', async (t) => {
  const db = await withSeededDatabase(t)

  const entries = await db.selectFrom('ledgerEntries').select('entryType').execute()
  const byType = countBy(entries, (entry) => entry.entryType)

  assert.equal(byType.held, 3)
  assert.equal(byType.released, 1)
  assert.equal(byType.paid_out, 1)

  const payouts = await db.selectFrom('payouts').selectAll().execute()
  const deliveredFulfillment = await db
    .selectFrom('fulfillments')
    .selectAll()
    .where('status', '=', 'delivered')
    .executeTakeFirstOrThrow()

  assert.equal(payouts.length, 1)
  assert.equal(payouts[0]?.sellerId, deliveredFulfillment.sellerId)
  assert.equal(payouts[0]?.amountCents, deliveredFulfillment.netCents)
})

test('sellers and casey are notified as the order history unfolds', async (t) => {
  const db = await withSeededDatabase(t)

  const notifications = await db.selectFrom('notifications').select('subject').execute()

  assert.equal(notifications.length, 5)
  assert.equal(notifications.filter((row) => row.subject === 'Item sold').length, 3)
  assert.equal(notifications.filter((row) => row.subject === 'Order shipped').length, 2)
})

test('page views cover the 14 days up to now across all three sites', async (t) => {
  const db = await withSeededDatabase(t)

  const rows = await db.selectFrom('pageViewCounts').select(['site', 'day']).execute()

  assert.equal(rows.length, 14 * 7)
  assert.equal(new Set(rows.map((row) => row.site)).size, 3)
  assert.equal(new Set(rows.map((row) => row.day)).size, 14)
})

test('running it a second time skips the demo data and touches nothing', async (t) => {
  const db = await withSeededDatabase(t)

  const again = await seedDemoData({ db, clock })
  const sellers = await db.selectFrom('sellers').select('id').execute()

  assert.equal(again, null)
  assert.equal(sellers.length, 4)
})

test('the summary counts what it seeded', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())
  await migrateToLatest(db)
  await seedAdmins({ db, clock })

  const summary: SeedDemoDataSummary | null = await seedDemoData({ db, clock })

  assert.deepEqual(summary, {
    sellerCount: 4,
    listingCount: 29,
    customerCount: 5,
    orderCount: 3,
    pageViewRowCount: 98,
  })
})

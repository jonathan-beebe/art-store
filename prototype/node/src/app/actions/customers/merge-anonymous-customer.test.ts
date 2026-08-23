import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sql } from 'kysely'
import type { AppDatabase } from '../../db/database.ts'
import { buildTestApp, TEST_INSTANT, type TestApp } from '../../test/build-test-app.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'
import { mergeAnonymousCustomer } from './merge-anonymous-customer.ts'
import { runInTransaction } from '../transaction.ts'

const NOW = TEST_INSTANT.toISOString()

/** The two customers a merge folds together, plus the app they live in. */
type Merging = TestApp & { anonymousCustomerId: number; verifiedCustomerId: number }

async function startMerging(): Promise<Merging> {
  const testApp = await buildTestApp()
  const anonymous = await createAnonymousCustomer(testApp)
  const verified = await createAnonymousCustomer(testApp)

  await testApp.db
    .updateTable('customers')
    .set({ email: 'buyer@example.com', emailVerifiedAt: NOW })
    .where('id', '=', verified.id)
    .execute()

  return { ...testApp, anonymousCustomerId: anonymous.id, verifiedCustomerId: verified.id }
}

async function merge(merging: Merging): Promise<void> {
  await mergeAnonymousCustomer(merging, {
    anonymousCustomerId: merging.anonymousCustomerId,
    verifiedCustomerId: merging.verifiedCustomerId,
  })
}

async function createListing(db: AppDatabase, title: string, quantity: number): Promise<number> {
  const seller = await db
    .insertInto('sellers')
    .values({ email: `${title}@example.com`, name: null, shopName: null, emailVerifiedAt: NOW, createdAt: NOW })
    .returning('id')
    .executeTakeFirstOrThrow()

  const listing = await sql<{ id: number }>`
    insert into listings (seller_id, title, slug, price_cents, quantity, status, created_at, updated_at)
    values (${seller.id}, ${title}, ${title}, 1000, ${quantity}, 'for_sale', ${NOW}, ${NOW})
    returning id
  `.execute(db)

  return listing.rows[0]?.id ?? 0
}

async function favorite(db: AppDatabase, customerId: number, listingId: number): Promise<void> {
  await sql`
    insert into favorites (customer_id, listing_id, created_at) values (${customerId}, ${listingId}, ${NOW})
  `.execute(db)
}

async function readFavoriteListingIds(db: AppDatabase, customerId: number): Promise<number[]> {
  const rows = await sql<{ listingId: number }>`
    select listing_id from favorites where customer_id = ${customerId} order by listing_id
  `.execute(db)

  return rows.rows.map((row) => row.listingId)
}

async function fillCart(
  db: AppDatabase,
  customerId: number,
  lines: readonly { listingId: number; quantity: number }[],
): Promise<void> {
  const cart = await sql<{ id: number }>`
    insert into carts (customer_id, created_at) values (${customerId}, ${NOW}) returning id
  `.execute(db)
  const cartId = cart.rows[0]?.id ?? 0

  for (const line of lines) {
    await sql`
      insert into cart_items (cart_id, listing_id, quantity)
      values (${cartId}, ${line.listingId}, ${line.quantity})
    `.execute(db)
  }
}

async function readCart(
  db: AppDatabase,
  customerId: number,
): Promise<{ listingId: number; quantity: number }[]> {
  const rows = await sql<{ listingId: number; quantity: number }>`
    select cart_items.listing_id, cart_items.quantity
    from cart_items join carts on carts.id = cart_items.cart_id
    where carts.customer_id = ${customerId}
    order by cart_items.listing_id
  `.execute(db)

  return rows.rows.map((row) => ({ listingId: row.listingId, quantity: row.quantity }))
}

async function countCarts(db: AppDatabase, customerId: number): Promise<number> {
  const rows = await sql<{ total: number }>`
    select count(*) as total from carts where customer_id = ${customerId}
  `.execute(db)

  return rows.rows[0]?.total ?? 0
}

test('it records the merge so a stale cookie still resolves forward', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)

  await merge(merging)

  const trail = await merging.db.selectFrom('customerMerges').selectAll().execute()

  assert.equal(trail.length, 1)
  assert.equal(trail[0]?.anonymousCustomerId, merging.anonymousCustomerId)
  assert.equal(trail[0]?.customerId, merging.verifiedCustomerId)
})

test('it leaves the anonymous row in place so the trail leads somewhere', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)

  await merge(merging)

  const anonymous = await merging.db
    .selectFrom('customers')
    .selectAll()
    .where('id', '=', merging.anonymousCustomerId)
    .executeTakeFirst()

  assert.notEqual(anonymous, undefined)
})

test('it re-points the rows of a table the customer owns', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)
  const { db } = merging
  const listingId = await createListing(db, 'moved', 5)
  const bystander = await createAnonymousCustomer(merging)

  for (const customerId of [merging.anonymousCustomerId, bystander.id]) {
    await sql`
      insert into listing_events (listing_id, customer_id, event_type, occurred_at)
      values (${listingId}, ${customerId}, 'view', ${NOW})
    `.execute(db)
  }

  await merge(merging)

  const events = await sql<{ customerId: number }>`
    select customer_id from listing_events order by id
  `.execute(db)

  assert.deepEqual(
    events.rows.map((row) => row.customerId),
    [merging.verifiedCustomerId, bystander.id],
  )
})

test('it skips a table the schema does not have and still writes its trail', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)

  await sql`drop table conversations`.execute(merging.db)

  await merge(merging)

  assert.equal((await merging.db.selectFrom('customerMerges').selectAll().execute()).length, 1)
})

test('a conversation opened anonymously re-points to the verified customer', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)
  const { db } = merging

  const conversation = await db
    .insertInto('conversations')
    .values({
      kind: 'admin_customer',
      sellerId: null,
      customerId: merging.anonymousCustomerId,
      adminId: null,
      listingId: null,
      fulfillmentId: null,
      createdAt: NOW,
      lastMessageAt: NOW,
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  await merge(merging)

  const repointed = await db
    .selectFrom('conversations')
    .select('customerId')
    .where('id', '=', conversation.id)
    .executeTakeFirstOrThrow()

  assert.equal(repointed.customerId, merging.verifiedCustomerId)
})

test('favorites de-duplicate rather than doubling up', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)
  const { db } = merging
  const shared = await createListing(db, 'shared', 3)
  const onlyAnonymous = await createListing(db, 'only-anonymous', 3)

  await favorite(db, merging.verifiedCustomerId, shared)
  await favorite(db, merging.anonymousCustomerId, shared)
  await favorite(db, merging.anonymousCustomerId, onlyAnonymous)

  await merge(merging)

  assert.deepEqual(await readFavoriteListingIds(db, merging.verifiedCustomerId), [
    shared,
    onlyAnonymous,
  ])
  assert.deepEqual(await readFavoriteListingIds(db, merging.anonymousCustomerId), [])
})

test('cart quantities sum when both customers hold the same listing', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)
  const { db } = merging
  const shared = await createListing(db, 'shared', 10)
  const onlyAnonymous = await createListing(db, 'only-anonymous', 10)

  await fillCart(db, merging.verifiedCustomerId, [{ listingId: shared, quantity: 2 }])
  await fillCart(db, merging.anonymousCustomerId, [
    { listingId: shared, quantity: 3 },
    { listingId: onlyAnonymous, quantity: 1 },
  ])

  await merge(merging)

  assert.deepEqual(await readCart(db, merging.verifiedCustomerId), [
    { listingId: shared, quantity: 5 },
    { listingId: onlyAnonymous, quantity: 1 },
  ])
})

test('a summed quantity is clamped to the stock the listing has left', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)
  const { db } = merging
  const scarce = await createListing(db, 'scarce', 3)

  await fillCart(db, merging.verifiedCustomerId, [{ listingId: scarce, quantity: 2 }])
  await fillCart(db, merging.anonymousCustomerId, [{ listingId: scarce, quantity: 2 }])

  await merge(merging)

  assert.deepEqual(await readCart(db, merging.verifiedCustomerId), [
    { listingId: scarce, quantity: 3 },
  ])
})

test('the fold leaves the account with exactly one cart', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)
  const { db } = merging
  const listingId = await createListing(db, 'one-cart', 4)

  await fillCart(db, merging.verifiedCustomerId, [{ listingId, quantity: 1 }])
  await fillCart(db, merging.anonymousCustomerId, [{ listingId, quantity: 1 }])

  await merge(merging)

  assert.equal(await countCarts(db, merging.verifiedCustomerId), 1)
  assert.equal(await countCarts(db, merging.anonymousCustomerId), 0)
})

test('an anonymous cart moves whole when the account has none', async (t) => {
  const merging = await startMerging()
  t.after(merging.close)
  const { db } = merging
  const listingId = await createListing(db, 'moving', 9)

  await fillCart(db, merging.anonymousCustomerId, [{ listingId, quantity: 2 }])

  await merge(merging)

  assert.equal(await countCarts(db, merging.verifiedCustomerId), 1)
  assert.deepEqual(await readCart(db, merging.verifiedCustomerId), [{ listingId, quantity: 2 }])
})

test("merging inside the caller's transaction joins it", async (t) => {
  const merging = await startMerging()
  t.after(merging.close)

  await runInTransaction(merging, async (context) =>
    mergeAnonymousCustomer(context, {
      anonymousCustomerId: merging.anonymousCustomerId,
      verifiedCustomerId: merging.verifiedCustomerId,
    }),
  )

  const merges = await merging.db.selectFrom('customerMerges').selectAll().execute()

  assert.equal(merges.length, 1)
})

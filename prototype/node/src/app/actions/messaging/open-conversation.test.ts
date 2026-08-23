import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openConversation } from './open-conversation.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import { createListing } from '../listings/create-listing.ts'
import { findAdminByEmail } from '../auth/find-admin-by-email.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../../db/database.ts'
import { seedAdmins } from '../../db/seed-admins.ts'
import { migrateToLatest } from '../../db/migrator.ts'

const NOW = new Date('2026-08-22T10:00:00.000Z')

const DEFAULT_DRAFT: ListingDraft = {
  title: 'Harbour at Dusk',
  description: 'Oil on canvas.',
  medium: 'Oil',
  dimensions: '40 x 60 cm',
  priceCents: 45_000,
  quantity: 2,
}

async function openWorld(): Promise<{ db: AppDatabase; context: ActionContext; close: () => Promise<void> }> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)
  return { db, context: { db, clock: fixedClock(NOW) }, close: () => db.destroy() }
}

async function seller(context: ActionContext, email = 'shop@example.test') {
  return claimSellerIdentity(context, email)
}

async function customer(context: ActionContext, email = 'buyer@example.test') {
  return claimCustomerIdentity(context, { email, currentCustomerId: null })
}

async function admin(context: ActionContext) {
  await seedAdmins(context)
  const found = await findAdminByEmail(context, 'jonathan-beebe@outlook.com')
  if (found === null) throw new Error('admin not seeded')
  return found
}

async function insertFulfillment(db: AppDatabase, sellerId: number, customerId: number): Promise<number> {
  const order = await db
    .insertInto('orders')
    .values({
      customerId,
      email: null,
      status: 'paid',
      shippingName: 'Ada Lovelace',
      shippingLine1: '12 Analytical Way',
      shippingLine2: null,
      shippingCity: 'London',
      shippingRegion: 'Greater London',
      shippingPostalCode: 'EC1A 1BB',
      shippingCountry: 'GB',
      subtotalCents: 45_000,
      totalCents: 45_000,
      placedAt: NOW.toISOString(),
      finalizedAt: NOW.toISOString(),
      cancelledAt: null,
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  const fulfillment = await db
    .insertInto('fulfillments')
    .values({
      orderId: order.id,
      sellerId,
      status: 'awaiting_shipment',
      carrier: null,
      trackingNumber: null,
      subtotalCents: 45_000,
      feeCents: 4_500,
      netCents: 40_500,
      shippedAt: null,
      deliveredAt: null,
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  return fulfillment.id
}

test('it opens an admin_seller conversation naming both sides', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const support = await admin(world.context)
  const shop = await seller(world.context)

  const conversation = await openConversation(world.context, {
    kind: 'admin_seller',
    adminId: support.id,
    sellerId: shop.id,
  })

  assert.equal(conversation.adminId, support.id)
  assert.equal(conversation.sellerId, shop.id)
  assert.equal(conversation.customerId, null)
  assert.equal(conversation.createdAt, conversation.lastMessageAt)
})

test('it opens an admin_customer conversation naming both sides', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const support = await admin(world.context)
  const buyer = await customer(world.context)

  const conversation = await openConversation(world.context, {
    kind: 'admin_customer',
    adminId: support.id,
    customerId: buyer.id,
  })

  assert.equal(conversation.adminId, support.id)
  assert.equal(conversation.customerId, buyer.id)
  assert.equal(conversation.sellerId, null)
  assert.equal(conversation.createdAt, conversation.lastMessageAt)
})

test('it opens a listing_question conversation naming the listing', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })

  const conversation = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: art.id,
  })

  assert.equal(conversation.sellerId, shop.id)
  assert.equal(conversation.customerId, buyer.id)
  assert.equal(conversation.listingId, art.id)
  assert.equal(conversation.createdAt, conversation.lastMessageAt)
})

test('it opens a fulfillment conversation naming the fulfillment', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const fulfillmentId = await insertFulfillment(world.db, shop.id, buyer.id)

  const conversation = await openConversation(world.context, {
    kind: 'fulfillment',
    sellerId: shop.id,
    customerId: buyer.id,
    fulfillmentId,
  })

  assert.equal(conversation.sellerId, shop.id)
  assert.equal(conversation.customerId, buyer.id)
  assert.equal(conversation.fulfillmentId, fulfillmentId)
  assert.equal(conversation.createdAt, conversation.lastMessageAt)
})

test('a second call on the same subject reuses the row', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const opening = {
    kind: 'listing_question' as const,
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: art.id,
  }

  const first = await openConversation(world.context, opening)
  const second = await openConversation(world.context, opening)

  assert.equal(second.id, first.id)
  const rows = await world.db.selectFrom('conversations').selectAll().execute()
  assert.equal(rows.length, 1)
})

test('a different listing opens a second row', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const first = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const second = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })

  const a = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: first.id,
  })
  const b = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: second.id,
  })

  assert.notEqual(a.id, b.id)
  const rows = await world.db.selectFrom('conversations').selectAll().execute()
  assert.equal(rows.length, 2)
})

test('a different seller opens a second row', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shopOne = await seller(world.context, 'one@example.test')
  const shopTwo = await seller(world.context, 'two@example.test')
  const buyer = await customer(world.context)
  const listingOne = await createListing(world.context, { sellerId: shopOne.id, draft: DEFAULT_DRAFT })
  const listingTwo = await createListing(world.context, { sellerId: shopTwo.id, draft: DEFAULT_DRAFT })

  const a = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shopOne.id,
    customerId: buyer.id,
    listingId: listingOne.id,
  })
  const b = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shopTwo.id,
    customerId: buyer.id,
    listingId: listingTwo.id,
  })

  assert.notEqual(a.id, b.id)
})

test('a kind missing a required participant throws TypeError', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)

  await assert.rejects(
    () => openConversation(world.context, { kind: 'listing_question', sellerId: shop.id }),
    TypeError,
  )
})

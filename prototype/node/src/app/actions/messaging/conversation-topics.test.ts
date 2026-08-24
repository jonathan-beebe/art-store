import { test } from 'node:test'
import assert from 'node:assert/strict'
import type {
  CustomerId,
  FulfillmentId,
  OrderId,
  SellerId,
} from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import { conversationTopicOf, conversationTopics } from './conversation-topics.ts'
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
import { cents } from '../../core/money.ts'

const NOW = new Date('2026-08-22T10:00:00.000Z')

const DEFAULT_DRAFT: ListingDraft = {
  title: 'Harbour at Dusk',
  description: 'Oil on canvas.',
  medium: 'Oil',
  dimensions: '40 x 60 cm',
  priceCents: cents(45_000),
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

async function insertFulfillment(
  db: AppDatabase,
  sellerId: SellerId,
  customerId: CustomerId,
): Promise<{ id: FulfillmentId; orderId: OrderId }> {
  const order = await db
    .insertInto('orders')
    .values({
      id: newId('ord', new Date()),
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
      id: newId('ful', new Date()),
      orderId: order.id,
      sellerId,
      status: 'awaiting_shipment',
      carrier: null,
      trackingNumber: null,
      subtotalCents: 45_000,
      feeCents: 4_500,
      netCents: 40_500,
      createdAt: NOW.toISOString(),
      shippedAt: null,
      deliveredAt: null,
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  return { id: fulfillment.id, orderId: order.id }
}

test('conversationTopics names the two admin kinds Art Store support', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const support = await admin(world.context)
  const buyer = await customer(world.context)
  const sellerDesk = await openConversation(world.context, {
    kind: 'admin_seller',
    adminId: support.id,
    sellerId: shop.id,
  })
  const customerDesk = await openConversation(world.context, {
    kind: 'admin_customer',
    adminId: support.id,
    customerId: buyer.id,
  })

  const topics = await conversationTopics(world.context, [sellerDesk, customerDesk])

  assert.equal(topics.get(sellerDesk.id), 'Art Store support')
  assert.equal(topics.get(customerDesk.id), 'Art Store support')
})

test('conversationTopics names a fulfillment thread by its order number', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const fulfillment = await insertFulfillment(world.db, shop.id, buyer.id)
  const conversation = await openConversation(world.context, {
    kind: 'fulfillment',
    sellerId: shop.id,
    customerId: buyer.id,
    fulfillmentId: fulfillment.id,
  })

  const topics = await conversationTopics(world.context, [conversation])

  assert.equal(topics.get(conversation.id), `order ${fulfillment.orderId}`)
})

test('conversationTopics names a listing question by the quoted listing title', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const art = await createListing(world.context, {
    sellerId: shop.id,
    draft: { ...DEFAULT_DRAFT, title: 'Harbour at Dusk' },
  })
  const conversation = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: art.id,
  })

  const topics = await conversationTopics(world.context, [conversation])

  assert.equal(topics.get(conversation.id), '“Harbour at Dusk”')
})

test('conversationTopicOf answers for one conversation', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const support = await admin(world.context)
  const conversation = await openConversation(world.context, {
    kind: 'admin_seller',
    adminId: support.id,
    sellerId: shop.id,
  })

  const topic = await conversationTopicOf(world.context, conversation)

  assert.equal(topic, 'Art Store support')
})

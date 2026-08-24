import { test } from 'node:test'
import assert from 'node:assert/strict'
import type {
  CustomerId,
  FulfillmentId,
  SellerId,
} from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import { participantNames } from './conversation-participants.ts'
import { openConversation } from './open-conversation.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import { createAnonymousCustomer } from '../customers/create-anonymous-customer.ts'
import { createListing } from '../listings/create-listing.ts'
import { findAdminByEmail } from '../auth/find-admin-by-email.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import { counterpartName, senderName } from '../../core/messaging/participant-name.ts'
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

async function namedSeller(world: { db: AppDatabase; context: ActionContext }, shopName: string) {
  const shop = await seller(world.context)
  await world.db.updateTable('sellers').set({ shopName }).where('id', '=', shop.id).execute()
  return shop
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
): Promise<FulfillmentId> {
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

  return fulfillment.id
}

test('participantNames names a seller by shop name', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await namedSeller(world, 'Blue Kiln Studio')
  const buyer = await customer(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const conversation = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: art.id,
  })

  const names = await participantNames(world.context, [conversation])

  assert.equal(names.seller.get(shop.id), 'Blue Kiln Studio')
})

test('participantNames names a customer by name, then address, then Guest id', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const support = await admin(world.context)
  const named = await customer(world.context, 'ada@example.test')
  await world.db.updateTable('customers').set({ name: 'Ada Lovelace' }).where('id', '=', named.id).execute()
  const addressed = await customer(world.context, 'grace@example.test')
  const guest = await createAnonymousCustomer(world.context)

  const namedConversation = await openConversation(world.context, {
    kind: 'admin_customer',
    adminId: support.id,
    customerId: named.id,
  })
  const addressedConversation = await openConversation(world.context, {
    kind: 'admin_customer',
    adminId: support.id,
    customerId: addressed.id,
  })
  const guestConversation = await openConversation(world.context, {
    kind: 'admin_customer',
    adminId: support.id,
    customerId: guest.id,
  })

  const names = await participantNames(world.context, [namedConversation, addressedConversation, guestConversation])

  assert.equal(names.customer.get(named.id), 'Ada Lovelace')
  assert.equal(names.customer.get(addressed.id), 'grace@example.test')
  assert.equal(names.customer.get(guest.id), `Guest ${guest.id}`)
})

test('participantNames names an admin by name', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const support = await admin(world.context)
  const conversation = await openConversation(world.context, {
    kind: 'admin_seller',
    adminId: support.id,
    sellerId: shop.id,
  })

  const names = await participantNames(world.context, [conversation])

  assert.equal(names.admin.get(support.id), 'Jonathan Beebe')
})

test('counterpartName gives the other side of a listing_question thread', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await namedSeller(world, 'Blue Kiln Studio')
  const buyer = await customer(world.context, 'ada@example.test')
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const conversation = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: art.id,
  })
  const names = await participantNames(world.context, [conversation])

  assert.equal(counterpartName(conversation, { type: 'customer', id: buyer.id }, names), 'Blue Kiln Studio')
  assert.equal(counterpartName(conversation, { type: 'seller', id: shop.id }, names), 'ada@example.test')
})

test('counterpartName gives the other side of a fulfillment thread', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await namedSeller(world, 'Blue Kiln Studio')
  const buyer = await customer(world.context, 'ada@example.test')
  const fulfillmentId = await insertFulfillment(world.db, shop.id, buyer.id)
  const conversation = await openConversation(world.context, {
    kind: 'fulfillment',
    sellerId: shop.id,
    customerId: buyer.id,
    fulfillmentId,
  })
  const names = await participantNames(world.context, [conversation])

  assert.equal(counterpartName(conversation, { type: 'customer', id: buyer.id }, names), 'Blue Kiln Studio')
  assert.equal(counterpartName(conversation, { type: 'seller', id: shop.id }, names), 'ada@example.test')
})

test('counterpartName gives the other side of an admin_seller thread', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await namedSeller(world, 'Blue Kiln Studio')
  const support = await admin(world.context)
  const conversation = await openConversation(world.context, {
    kind: 'admin_seller',
    adminId: support.id,
    sellerId: shop.id,
  })
  const names = await participantNames(world.context, [conversation])

  assert.equal(counterpartName(conversation, { type: 'admin', id: support.id }, names), 'Blue Kiln Studio')
  assert.equal(counterpartName(conversation, { type: 'seller', id: shop.id }, names), 'Jonathan Beebe')
})

test('counterpartName gives the other side of an admin_customer thread', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const support = await admin(world.context)
  const buyer = await customer(world.context, 'ada@example.test')
  const conversation = await openConversation(world.context, {
    kind: 'admin_customer',
    adminId: support.id,
    customerId: buyer.id,
  })
  const names = await participantNames(world.context, [conversation])

  assert.equal(counterpartName(conversation, { type: 'admin', id: support.id }, names), 'ada@example.test')
  assert.equal(counterpartName(conversation, { type: 'customer', id: buyer.id }, names), 'Jonathan Beebe')
})

test("senderName names a message's sender", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await namedSeller(world, 'Blue Kiln Studio')
  const buyer = await customer(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const conversation = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: art.id,
  })
  const names = await participantNames(world.context, [conversation])

  assert.equal(senderName({ senderType: 'seller', senderId: shop.id }, names), 'Blue Kiln Studio')
})

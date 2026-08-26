import { test } from 'node:test'
import assert from 'node:assert/strict'
import type {
  CustomerId,
  FulfillmentId,
  SellerId,
} from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import { fixtureId } from '../../test/fixture-ids.ts'
import { conversationThread } from './conversation-thread.ts'
import { openConversation } from './open-conversation.ts'
import { postMessage } from './post-message.ts'
import { publishListingFaq } from './publish-listing-faq.ts'
import { blockCustomer } from '../moderation/block-customer.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import { createListing } from '../listings/create-listing.ts'
import { findAdminByEmail } from '../auth/find-admin-by-email.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import { mustSucceed } from '../../core/refusal.ts'
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

test('it returns messages oldest first with isMine set for the actor', async (t) => {
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
  const first = mustSucceed(
    await postMessage(world.context, {
      conversationId: conversation.id,
      sender: { type: 'customer', id: buyer.id },
      body: 'Is this still available?',
    }),
  ).message
  const second = mustSucceed(
    await postMessage(world.context, {
      conversationId: conversation.id,
      sender: { type: 'seller', id: shop.id },
      body: 'Yes!',
    }),
  ).message

  const thread = await conversationThread(world.context, {
    conversationId: conversation.id,
    actor: { type: 'customer', id: buyer.id },
  })

  assert.deepEqual(
    thread?.messages.map((message) => message.id),
    [first.id, second.id],
  )
  assert.equal(thread?.messages[0]?.isMine, true)
  assert.equal(thread?.messages[1]?.isMine, false)
})

test('mayPost is true for a participant', async (t) => {
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

  const thread = await conversationThread(world.context, {
    conversationId: conversation.id,
    actor: { type: 'customer', id: buyer.id },
  })

  assert.equal(thread?.mayPost, true)
})

test('mayPost is false for a blocked customer, who may still read', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const support = await admin(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const conversation = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyer.id,
    listingId: art.id,
  })
  mustSucceed(
    await blockCustomer(world.context, { customerId: buyer.id, adminId: support.id, reason: 'Chargeback fraud.' }),
  )

  const thread = await conversationThread(world.context, {
    conversationId: conversation.id,
    actor: { type: 'customer', id: buyer.id },
  })

  assert.notEqual(thread, null)
  assert.equal(thread?.mayPost, false)
})

test('it fills the listing subject for a listing_question conversation', async (t) => {
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

  const thread = await conversationThread(world.context, {
    conversationId: conversation.id,
    actor: { type: 'seller', id: shop.id },
  })

  assert.deepEqual(thread?.listing, { id: art.id, title: art.title, slug: art.slug })
  assert.equal(thread?.fulfillment, null)
})

test('it fills the fulfillment subject for a fulfillment conversation', async (t) => {
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

  const thread = await conversationThread(world.context, {
    conversationId: conversation.id,
    actor: { type: 'seller', id: shop.id },
  })

  assert.equal(thread?.fulfillment?.id, fulfillmentId)
  assert.equal(thread?.listing, null)
})

test('it returns null for a conversation the actor is not in', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyerA = await customer(world.context, 'ada@example.test')
  const buyerB = await customer(world.context, 'grace@example.test')
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const conversation = await openConversation(world.context, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyerA.id,
    listingId: art.id,
  })

  const thread = await conversationThread(world.context, {
    conversationId: conversation.id,
    actor: { type: 'customer', id: buyerB.id },
  })

  assert.equal(thread, null)
})

test('it returns null for an id that names nothing', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const buyer = await customer(world.context)

  const thread = await conversationThread(world.context, {
    conversationId: fixtureId('cnv', 999),
    actor: { type: 'customer', id: buyer.id },
  })

  assert.equal(thread, null)
})

test('a message published to the listing carries the FAQ id it was published as; the rest carry null', async (t) => {
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
  const question = mustSucceed(
    await postMessage(world.context, {
      conversationId: conversation.id,
      sender: { type: 'customer', id: buyer.id },
      body: 'Is this framed?',
    }),
  ).message
  const answer = mustSucceed(
    await postMessage(world.context, {
      conversationId: conversation.id,
      sender: { type: 'seller', id: shop.id },
      body: 'Yes, in oak.',
    }),
  ).message
  const faq = mustSucceed(
    await publishListingFaq(world.context, {
      listingId: art.id,
      draft: { question: 'Is this framed?', answer: 'Yes, in oak.' },
      sourceMessageId: answer.id,
    }),
  ).faq

  const thread = await conversationThread(world.context, {
    conversationId: conversation.id,
    actor: { type: 'seller', id: shop.id },
  })

  const questionMessage = thread?.messages.find((message) => message.id === question.id)
  const answerMessage = thread?.messages.find((message) => message.id === answer.id)
  assert.equal(questionMessage?.publishedFaqId, null)
  assert.equal(answerMessage?.publishedFaqId, faq.id)
})

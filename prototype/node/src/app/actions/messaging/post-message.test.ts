import { test } from 'node:test'
import assert from 'node:assert/strict'
import { postMessage } from './post-message.ts'
import { openConversation } from './open-conversation.ts'
import { blockCustomer } from '../moderation/block-customer.ts'
import { liftCustomerBlock } from '../moderation/lift-customer-block.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import { createListing } from '../listings/create-listing.ts'
import { findAdminByEmail } from '../auth/find-admin-by-email.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../../db/database.ts'
import { seedAdmins } from '../../db/seed-admins.ts'
import { migrateToLatest } from '../../db/migrator.ts'
import { cents } from '../../core/money.ts'

const NOW = new Date('2026-08-22T10:00:00.000Z')
const LATER = new Date('2026-08-22T10:05:00.000Z')

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

async function listingConversation(context: ActionContext, sellerId: number, customerId: number) {
  const listing = await createListing(context, { sellerId, draft: DEFAULT_DRAFT })
  return openConversation(context, {
    kind: 'listing_question',
    sellerId,
    customerId,
    listingId: listing.id,
  })
}

test("it appends a message with the sender's type and id and no read marker", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  const message = await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'customer', id: buyer.id },
    body: 'Is this still available?',
  })

  assert.equal(message.senderType, 'customer')
  assert.equal(message.senderId, buyer.id)
  assert.equal(message.body, 'Is this still available?')
  assert.equal(message.readAt, null)
})

test("it bumps the conversation's lastMessageAt to the clock", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)
  const laterContext: ActionContext = { db: world.db, clock: fixedClock(LATER) }

  await postMessage(laterContext, {
    conversationId: conversation.id,
    sender: { type: 'customer', id: buyer.id },
    body: 'Following up.',
  })

  const updated = await world.db
    .selectFrom('conversations')
    .selectAll()
    .where('id', '=', conversation.id)
    .executeTakeFirstOrThrow()
  assert.equal(updated.lastMessageAt, LATER.toISOString())
})

test("it notifies the seller at the seller's own message path", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'customer', id: buyer.id },
    body: 'Is this still available?',
  })

  const notifications = await world.db.selectFrom('notifications').selectAll().execute()
  assert.equal(notifications.length, 1)
  const [notification] = notifications
  assert.equal(notification?.sellerId, shop.id)
  assert.equal(notification?.subject, 'New message')
  assert.equal(notification?.url, `/seller/messages/${conversation.id}`)
})

test("it notifies the customer at the customer's own message path", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'seller', id: shop.id },
    body: 'Still here, still for sale.',
  })

  const notifications = await world.db.selectFrom('notifications').selectAll().execute()
  assert.equal(notifications.length, 1)
  const [notification] = notifications
  assert.equal(notification?.customerId, buyer.id)
  assert.equal(notification?.subject, 'New message')
  assert.equal(notification?.url, `/messages/${conversation.id}`)
})

test("it notifies the admin at the admin's own message path", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const support = await admin(world.context)
  const conversation = await openConversation(world.context, {
    kind: 'admin_seller',
    adminId: support.id,
    sellerId: shop.id,
  })

  await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'seller', id: shop.id },
    body: 'Need help with a listing.',
  })

  const notifications = await world.db.selectFrom('notifications').selectAll().execute()
  assert.equal(notifications.length, 1)
  const [notification] = notifications
  assert.equal(notification?.adminId, support.id)
  assert.equal(notification?.url, `/admin/messages/${conversation.id}`)
})

test('it refuses an empty body', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  await assert.rejects(
    () =>
      postMessage(world.context, {
        conversationId: conversation.id,
        sender: { type: 'customer', id: buyer.id },
        body: '   ',
      }),
    TransitionError,
  )
})

test('it refuses a body over 2000 characters', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  await assert.rejects(
    () =>
      postMessage(world.context, {
        conversationId: conversation.id,
        sender: { type: 'customer', id: buyer.id },
        body: 'x'.repeat(2_001),
      }),
    TransitionError,
  )
})

test('it refuses a sender who is not a participant', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const outsider = await admin(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  await assert.rejects(
    () =>
      postMessage(world.context, {
        conversationId: conversation.id,
        sender: { type: 'admin', id: outsider.id },
        body: 'Hello?',
      }),
    TransitionError,
  )
})

test('it refuses a customer with an active block', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const support = await admin(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)
  await blockCustomer(world.context, { customerId: buyer.id, adminId: support.id, reason: 'Chargeback fraud.' })

  await assert.rejects(
    () =>
      postMessage(world.context, {
        conversationId: conversation.id,
        sender: { type: 'customer', id: buyer.id },
        body: 'Still there?',
      }),
    TransitionError,
  )
})

test('a customer whose block was lifted may post again', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const support = await admin(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)
  await blockCustomer(world.context, { customerId: buyer.id, adminId: support.id, reason: 'Chargeback fraud.' })
  await liftCustomerBlock(world.context, { customerId: buyer.id })

  const message = await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'customer', id: buyer.id },
    body: 'Back again.',
  })

  assert.equal(message.body, 'Back again.')
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { markConversationRead } from './mark-conversation-read.ts'
import { openConversation } from './open-conversation.ts'
import { postMessage } from './post-message.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import { createListing } from '../listings/create-listing.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../../db/database.ts'
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

async function listingConversation(context: ActionContext, sellerId: number, customerId: number) {
  const listing = await createListing(context, { sellerId, draft: DEFAULT_DRAFT })
  return openConversation(context, {
    kind: 'listing_question',
    sellerId,
    customerId,
    listingId: listing.id,
  })
}

test('it marks only the messages the reader did not send and returns how many', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'seller', id: shop.id },
    body: 'Still available.',
  })
  const mine = await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'customer', id: buyer.id },
    body: 'Great, I will take it.',
  })
  await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'seller', id: shop.id },
    body: 'Shipping tomorrow.',
  })

  const markedCount = await markConversationRead(world.context, {
    conversationId: conversation.id,
    reader: { type: 'customer', id: buyer.id },
  })

  assert.equal(markedCount, 2)
  const messages = await world.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .orderBy('id')
    .execute()
  assert.equal(messages[0]?.readAt, NOW.toISOString())
  assert.equal(messages[1]?.id, mine.id)
  assert.equal(messages[1]?.readAt, null)
  assert.equal(messages[2]?.readAt, NOW.toISOString())
})

test('it leaves already-read messages alone and returns 0 the next time', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)
  await postMessage(world.context, {
    conversationId: conversation.id,
    sender: { type: 'seller', id: shop.id },
    body: 'Still available.',
  })

  const first = await markConversationRead(world.context, {
    conversationId: conversation.id,
    reader: { type: 'customer', id: buyer.id },
  })
  const second = await markConversationRead(world.context, {
    conversationId: conversation.id,
    reader: { type: 'customer', id: buyer.id },
  })

  assert.equal(first, 1)
  assert.equal(second, 0)
})

test('it returns 0 for a conversation with no messages', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const buyer = await customer(world.context)
  const conversation = await listingConversation(world.context, shop.id, buyer.id)

  const marked = await markConversationRead(world.context, {
    conversationId: conversation.id,
    reader: { type: 'customer', id: buyer.id },
  })

  assert.equal(marked, 0)
})

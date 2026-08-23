import { test } from 'node:test'
import assert from 'node:assert/strict'
import { inboxConversations, unreadMessageCount } from './conversation-inbox.ts'
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

const T1 = new Date('2026-08-22T10:00:00.000Z')
const T2 = new Date('2026-08-22T10:05:00.000Z')
const T3 = new Date('2026-08-22T10:10:00.000Z')

const DEFAULT_DRAFT: ListingDraft = {
  title: 'Harbour at Dusk',
  description: 'Oil on canvas.',
  medium: 'Oil',
  dimensions: '40 x 60 cm',
  priceCents: cents(45_000),
  quantity: 2,
}

async function openWorld(): Promise<{ db: AppDatabase; close: () => Promise<void> }> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)
  return { db, close: () => db.destroy() }
}

function contextAt(db: AppDatabase, instant: Date): ActionContext {
  return { db, clock: fixedClock(instant) }
}

async function seller(context: ActionContext, email = 'shop@example.test') {
  return claimSellerIdentity(context, email)
}

async function customer(context: ActionContext, email = 'buyer@example.test') {
  return claimCustomerIdentity(context, { email, currentCustomerId: null })
}

/**
 * Two threads for one seller: A is asked first but bumped to the top by a
 * later seller reply; B stays where it landed. The seller's own reply in A
 * is what proves preview and unread count read differently.
 */
async function seedTwoThreads(db: AppDatabase) {
  const ctx1 = contextAt(db, T1)
  const shop = await seller(ctx1, 'shop@example.test')
  const buyerA = await customer(ctx1, 'ada@example.test')
  const buyerB = await customer(ctx1, 'grace@example.test')
  const listingA = await createListing(ctx1, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const listingB = await createListing(ctx1, {
    sellerId: shop.id,
    draft: { ...DEFAULT_DRAFT, title: 'Coastline Study' },
  })
  const conversationA = await openConversation(ctx1, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyerA.id,
    listingId: listingA.id,
  })
  await postMessage(ctx1, {
    conversationId: conversationA.id,
    sender: { type: 'customer', id: buyerA.id },
    body: 'Is this available?',
  })

  const ctx2 = contextAt(db, T2)
  const conversationB = await openConversation(ctx2, {
    kind: 'listing_question',
    sellerId: shop.id,
    customerId: buyerB.id,
    listingId: listingB.id,
  })
  await postMessage(ctx2, {
    conversationId: conversationB.id,
    sender: { type: 'customer', id: buyerB.id },
    body: 'Do you ship to Canada?',
  })

  const ctx3 = contextAt(db, T3)
  await postMessage(ctx3, {
    conversationId: conversationA.id,
    sender: { type: 'seller', id: shop.id },
    body: 'Yes, still available!',
  })

  return { shop, conversationA, conversationB, ctx3 }
}

test('inboxConversations orders threads newest lastMessageAt first with topic, counterpart, preview, unread count, and path', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const { shop, conversationA, conversationB, ctx3 } = await seedTwoThreads(world.db)

  const inbox = await inboxConversations(ctx3, { type: 'seller', id: shop.id })

  assert.deepEqual(
    inbox.map((row) => row.id),
    [conversationA.id, conversationB.id],
  )
  const [first, second] = inbox
  assert.ok(first)
  assert.ok(second)
  assert.equal(first.counterpart, 'ada@example.test')
  assert.equal(first.topic, '“Harbour at Dusk”')
  assert.equal(first.preview, 'Yes, still available!')
  assert.equal(first.unreadCount, 1)
  assert.equal(first.path, `/seller/messages/${conversationA.id}`)
  assert.equal(second.counterpart, 'grace@example.test')
  assert.equal(second.preview, 'Do you ship to Canada?')
  assert.equal(second.unreadCount, 1)
})

test('inboxConversations does not include a thread the actor is not in', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const ctx = contextAt(world.db, T1)
  const shop = await seller(ctx, 'shop@example.test')
  const otherShop = await seller(ctx, 'other@example.test')
  const buyer = await customer(ctx)
  const listing = await createListing(ctx, { sellerId: otherShop.id, draft: DEFAULT_DRAFT })
  await openConversation(ctx, {
    kind: 'listing_question',
    sellerId: otherShop.id,
    customerId: buyer.id,
    listingId: listing.id,
  })

  const inbox = await inboxConversations(ctx, { type: 'seller', id: shop.id })

  assert.deepEqual(inbox, [])
})

test("unreadMessageCount totals unread across threads and excludes the actor's own messages", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const { shop, ctx3 } = await seedTwoThreads(world.db)

  const total = await unreadMessageCount(ctx3, { type: 'seller', id: shop.id })

  assert.equal(total, 2)
})

test('unreadMessageCount returns 0 for an actor with no threads', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const ctx = contextAt(world.db, T1)
  const shop = await seller(ctx)

  const total = await unreadMessageCount(ctx, { type: 'seller', id: shop.id })

  assert.equal(total, 0)
})

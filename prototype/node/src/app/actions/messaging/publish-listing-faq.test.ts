import { test } from 'node:test'
import assert from 'node:assert/strict'
import { publishListingFaq, publishedFaq } from './publish-listing-faq.ts'
import { openConversation } from './open-conversation.ts'
import { postMessage, postedMessage } from './post-message.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import { createListing } from '../listings/create-listing.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import { BrokenContractError } from '../../core/defect.ts'
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

test('it publishes a question and answer, recording the source message', async (t) => {
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
  const question = postedMessage(
    await postMessage(world.context, {
      conversationId: conversation.id,
      sender: { type: 'customer', id: buyer.id },
      body: 'Is this framed?',
    }),
  )

  const faq = publishedFaq(
    await publishListingFaq(world.context, {
      listingId: art.id,
      draft: { question: 'Is this framed?', answer: 'Yes, in a natural oak frame.' },
      sourceMessageId: question.id,
    }),
  )

  assert.equal(faq.listingId, art.id)
  assert.equal(faq.question, 'Is this framed?')
  assert.equal(faq.answer, 'Yes, in a natural oak frame.')
  assert.equal(faq.sourceMessageId, question.id)
  assert.equal(faq.publishedAt, NOW.toISOString())
})

test('it publishes with no source message', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })

  const faq = publishedFaq(
    await publishListingFaq(world.context, {
      listingId: art.id,
      draft: { question: 'Does it ship internationally?', answer: 'Yes, worldwide.' },
    }),
  )

  assert.equal(faq.sourceMessageId, null)
})

test('publishing the same source message twice refuses the second attempt', async (t) => {
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
  const question = postedMessage(
    await postMessage(world.context, {
      conversationId: conversation.id,
      sender: { type: 'customer', id: buyer.id },
      body: 'Is this framed?',
    }),
  )
  const firstFaq = publishedFaq(
    await publishListingFaq(world.context, {
      listingId: art.id,
      draft: { question: 'Is this framed?', answer: 'Yes, in a natural oak frame.' },
      sourceMessageId: question.id,
    }),
  )

  const result = await publishListingFaq(world.context, {
    listingId: art.id,
    draft: { question: 'Is this framed?', answer: 'Yes, in a natural oak frame.' },
    sourceMessageId: question.id,
  })

  assert.equal(result.outcome, 'refused')
  assert.equal(result.reason, 'already_published')
  assert.deepEqual(result.data, {
    listing_id: art.id,
    source_message_id: question.id,
    listing_faq_id: firstFaq.id,
  })

  const rows = await world.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', art.id)
    .execute()
  assert.equal(rows.length, 1)
})

test('publishedFaq throws for a refusal, carrying its reason', async (t) => {
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
  const question = postedMessage(
    await postMessage(world.context, {
      conversationId: conversation.id,
      sender: { type: 'customer', id: buyer.id },
      body: 'Is this framed?',
    }),
  )
  publishedFaq(
    await publishListingFaq(world.context, {
      listingId: art.id,
      draft: { question: 'Is this framed?', answer: 'Yes, in a natural oak frame.' },
      sourceMessageId: question.id,
    }),
  )

  const result = await publishListingFaq(world.context, {
    listingId: art.id,
    draft: { question: 'Is this framed?', answer: 'Yes, in a natural oak frame.' },
    sourceMessageId: question.id,
  })

  assert.throws(
    () => publishedFaq(result),
    (error: unknown) => error instanceof BrokenContractError && error.reason === 'already_published',
  )
})

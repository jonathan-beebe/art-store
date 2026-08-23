import { test } from 'node:test'
import assert from 'node:assert/strict'
import { findListingFaq, listingFaqs } from './listing-faqs.ts'
import { publishListingFaq } from './publish-listing-faq.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { createListing } from '../listings/create-listing.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../../db/database.ts'
import { migrateToLatest } from '../../db/migrator.ts'

const NOW = new Date('2026-08-22T10:00:00.000Z')
const LATER = new Date('2026-08-22T10:05:00.000Z')

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

test("listingFaqs returns a listing's entries oldest first", async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const earlyContext: ActionContext = { db: world.db, clock: fixedClock(NOW) }
  const laterContext: ActionContext = { db: world.db, clock: fixedClock(LATER) }

  const first = await publishListingFaq(earlyContext, {
    listingId: art.id,
    draft: { question: 'Is it framed?', answer: 'Yes.' },
  })
  const second = await publishListingFaq(laterContext, {
    listingId: art.id,
    draft: { question: 'Does it ship?', answer: 'Yes, worldwide.' },
  })

  const entries = await listingFaqs(world.context, art.id)

  assert.deepEqual(
    entries.map((entry) => entry.id),
    [first.id, second.id],
  )
})

test('listingFaqs returns nothing from another listing', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const artOne = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const artTwo = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  await publishListingFaq(world.context, {
    listingId: artOne.id,
    draft: { question: 'Is it framed?', answer: 'Yes.' },
  })

  const entries = await listingFaqs(world.context, artTwo.id)

  assert.deepEqual(entries, [])
})

test('findListingFaq returns the entry that belongs to the listing', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const faq = await publishListingFaq(world.context, {
    listingId: art.id,
    draft: { question: 'Is it framed?', answer: 'Yes.' },
  })

  const found = await findListingFaq(world.context, { faqId: faq.id, listingId: art.id })

  assert.equal(found?.id, faq.id)
})

test('findListingFaq returns null for an id on a different listing', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const artOne = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const artTwo = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const faq = await publishListingFaq(world.context, {
    listingId: artOne.id,
    draft: { question: 'Is it framed?', answer: 'Yes.' },
  })

  const found = await findListingFaq(world.context, { faqId: faq.id, listingId: artTwo.id })

  assert.equal(found, null)
})

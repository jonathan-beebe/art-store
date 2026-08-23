import { test } from 'node:test'
import assert from 'node:assert/strict'
import { unpublishListingFaq } from './unpublish-listing-faq.ts'
import { publishListingFaq } from './publish-listing-faq.ts'
import { listingFaqs } from './listing-faqs.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
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

test('it deletes the row so listingFaqs no longer returns it', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)
  const art = await createListing(world.context, { sellerId: shop.id, draft: DEFAULT_DRAFT })
  const faq = await publishListingFaq(world.context, {
    listingId: art.id,
    draft: { question: 'Is this framed?', answer: 'Yes.' },
  })

  await unpublishListingFaq(world.context, faq.id)

  const remaining = await listingFaqs(world.context, art.id)
  assert.deepEqual(remaining, [])
  const rows = await world.db.selectFrom('listingFaqs').selectAll().execute()
  assert.equal(rows.length, 0)
})

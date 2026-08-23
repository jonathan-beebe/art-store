import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createListing } from './create-listing.ts'
import { updateListing } from './update-listing.ts'
import { parseListingDraft, type ListingDraft, type ListingDraftFields } from '../../core/listings/listing-draft.ts'
import { createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('it writes the edited fields', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, { sellerId, draft: draft() })

  const updated = await updateListing(context, {
    listingId: art.id,
    draft: draft({ title: 'Harbour at Dawn', price: '300.50' }),
  })

  assert.equal(updated.title, 'Harbour at Dawn')
  assert.equal(updated.priceCents, 30_050)
})

test('a retitled listing keeps its slug', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, { sellerId, draft: draft() })

  const updated = await updateListing(context, {
    listingId: art.id,
    draft: draft({ title: 'Harbour at Dawn' }),
  })

  assert.equal(updated.slug, 'harbour-at-dusk')
})

test('it leaves the status alone', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, { sellerId, draft: draft() })
  await world.db.updateTable('listings').set({ status: 'for_sale' }).where('id', '=', art.id).execute()

  const updated = await updateListing(context, {
    listingId: art.id,
    draft: draft({ title: 'Harbour at Dawn' }),
  })

  assert.equal(updated.status, 'for_sale')
})

test('passing imagePath replaces the image', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, { sellerId, draft: draft(), imagePath: '/uploads/first.png' })

  const updated = await updateListing(context, {
    listingId: art.id,
    draft: draft(),
    imagePath: '/uploads/second.png',
  })

  assert.equal(updated.imagePath, '/uploads/second.png')
})

test('leaving imagePath out keeps the image the listing has', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, { sellerId, draft: draft(), imagePath: '/uploads/first.png' })

  const updated = await updateListing(context, {
    listingId: art.id,
    draft: draft({ title: 'Harbour at Dawn' }),
  })

  assert.equal(updated.imagePath, '/uploads/first.png')
})

test('updatedAt moves', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, { sellerId, draft: draft() })

  world.travelTo(new Date('2026-08-21T09:00:00.000Z'))
  const updated = await updateListing(context, {
    listingId: art.id,
    draft: draft({ title: 'Harbour at Dawn' }),
  })

  assert.notEqual(updated.updatedAt, art.updatedAt)
  assert.equal(updated.updatedAt, '2026-08-21T09:00:00.000Z')
})

function draft(overrides: ListingDraftFields = {}): ListingDraft {
  const parsed = parseListingDraft({
    title: 'Harbour at Dusk',
    description: 'Oil on canvas.',
    medium: 'Oil',
    dimensions: '40 x 60 cm',
    price: '249.00',
    quantity: '2',
    ...overrides,
  })
  if (!parsed.ok) throw new Error(`the fixture draft is not valid: ${JSON.stringify(parsed.errors)}`)

  return parsed.value
}

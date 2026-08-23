import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createListing } from './create-listing.ts'
import { parseListingDraft, type ListingDraft, type ListingDraftFields } from '../../core/listings/listing-draft.ts'
import { createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('a new listing starts as a draft', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)

  const created = await createListing(context, { sellerId, draft: draft() })

  assert.equal(created.status, 'draft')
})

test('it stores the typed fields with the price in cents', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)

  const created = await createListing(context, { sellerId, draft: draft() })

  assert.equal(created.title, 'Harbour at Dusk')
  assert.equal(created.description, 'Oil on canvas.')
  assert.equal(created.medium, 'Oil')
  assert.equal(created.dimensions, '40 x 60 cm')
  assert.equal(created.priceCents, 24_900)
  assert.equal(created.quantity, 2)
})

test('it belongs to the seller who created it', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)

  const created = await createListing(context, { sellerId, draft: draft() })

  assert.equal(created.sellerId, sellerId)
})

test('it slugs the title', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)

  const created = await createListing(context, { sellerId, draft: draft() })

  assert.equal(created.slug, 'harbour-at-dusk')
})

test('a title another listing already slugged is numbered', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  await createListing(context, { sellerId, draft: draft() })

  const created = await createListing(context, { sellerId, draft: draft() })

  assert.equal(created.slug, 'harbour-at-dusk-2')
})

test('an imagePath is stored', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)

  const created = await createListing(context, {
    sellerId,
    draft: draft(),
    imagePath: '/uploads/harbour.png',
  })

  assert.equal(created.imagePath, '/uploads/harbour.png')
})

test('a listing with no upload carries null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)

  const created = await createListing(context, { sellerId, draft: draft() })

  assert.equal(created.imagePath, null)
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

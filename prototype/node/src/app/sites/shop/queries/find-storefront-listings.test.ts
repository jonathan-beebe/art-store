import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listPage } from '../../../core/paging/list-page.ts'
import type { ListingSearch } from '../../../core/shop/listing-search.ts'
import {
  createListing,
  createSeller,
  openCommerceWorld,
} from '../../../test/commerce-world.ts'
import {
  countStorefrontListings,
  findStorefrontListings,
  findStorefrontMedia,
} from './find-storefront-listings.ts'

const NO_SEARCH: ListingSearch = { term: null, medium: null }

function pageOf(totalCount: number, requested: string | number | null = null, size = 12) {
  return listPage({ requested, size, totalCount })
}

test('it shows only for-sale listings with no active removal, newest first', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { title: 'Draft', status: 'draft' })
  await createListing(world.context, sellerId, { title: 'Archived', status: 'archived' })
  const older = await createListing(world.context, sellerId, { title: 'Older', status: 'for_sale' })
  const newer = await createListing(world.context, sellerId, { title: 'Newer', status: 'for_sale' })

  const listings = await findStorefrontListings(world.db, { search: NO_SEARCH, page: pageOf(2) })

  assert.deepEqual(listings.map((listing) => listing.id), [newer.id, older.id])
})

test('a search term matches the title', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  const wanted = await createListing(world.context, sellerId, { title: 'Harbour at Dusk' })
  await createListing(world.context, sellerId, { title: 'Morning Tide' })

  const search: ListingSearch = { term: 'harbour', medium: null }
  const listings = await findStorefrontListings(world.db, { search, page: pageOf(1) })

  assert.deepEqual(listings.map((listing) => listing.id), [wanted.id])
})

test('a search term matches the medium too', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  const wanted = await createListing(world.context, sellerId, { title: 'Harbour at Dusk', medium: 'Watercolour' })
  await createListing(world.context, sellerId, { title: 'Morning Tide', medium: 'Oil' })

  const search: ListingSearch = { term: 'watercolour', medium: null }
  const listings = await findStorefrontListings(world.db, { search, page: pageOf(1) })

  assert.deepEqual(listings.map((listing) => listing.id), [wanted.id])
})

test('a search with no match returns nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { title: 'Harbour at Dusk' })

  const search: ListingSearch = { term: 'sculpture', medium: null }
  const listings = await findStorefrontListings(world.db, { search, page: pageOf(0) })

  assert.deepEqual(listings, [])
})

test('the medium filter narrows to an exact match', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  const wanted = await createListing(world.context, sellerId, { title: 'Harbour at Dusk', medium: 'Oil' })
  await createListing(world.context, sellerId, { title: 'Morning Tide', medium: 'Watercolour' })

  const search: ListingSearch = { term: null, medium: 'Oil' }
  const listings = await findStorefrontListings(world.db, { search, page: pageOf(1) })

  assert.deepEqual(listings.map((listing) => listing.id), [wanted.id])
})

test('the second page picks up where the first left off', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  const first = await createListing(world.context, sellerId, { title: 'First' })
  const second = await createListing(world.context, sellerId, { title: 'Second' })
  const third = await createListing(world.context, sellerId, { title: 'Third' })

  const total = await countStorefrontListings(world.db, NO_SEARCH)
  const firstPage = await findStorefrontListings(world.db, { search: NO_SEARCH, page: pageOf(total, 1, 2) })
  const secondPage = await findStorefrontListings(world.db, { search: NO_SEARCH, page: pageOf(total, 2, 2) })

  assert.deepEqual(firstPage.map((listing) => listing.id), [third.id, second.id])
  assert.deepEqual(secondPage.map((listing) => listing.id), [first.id])
})

test('a page past the end comes back empty', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { title: 'Only One' })

  // `listPage` clamps an out-of-range request to the last real page, so an
  // offset past the end is reached directly, the way a stale bookmark would.
  const listings = await findStorefrontListings(world.db, {
    search: NO_SEARCH,
    page: { ...pageOf(1), offset: 50, limit: 12 },
  })

  assert.deepEqual(listings, [])
})

test('countStorefrontListings counts what the search matches, not the page size', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { title: 'One' })
  await createListing(world.context, sellerId, { title: 'Two' })
  await createListing(world.context, sellerId, { title: 'Three' })

  const total = await countStorefrontListings(world.db, NO_SEARCH)

  assert.equal(total, 3)
})

test('findStorefrontMedia lists the distinct media on offer, alphabetically', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { title: 'A', medium: 'Watercolour' })
  await createListing(world.context, sellerId, { title: 'B', medium: 'Oil' })
  await createListing(world.context, sellerId, { title: 'C', medium: 'Oil' })
  await createListing(world.context, sellerId, { title: 'Draft', medium: 'Ink', status: 'draft' })

  const media = await findStorefrontMedia(world.db)

  assert.deepEqual(media, ['Oil', 'Watercolour'])
})

test('findStorefrontMedia leaves out a blank medium', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { title: 'No medium', medium: '' })

  const media = await findStorefrontMedia(world.db)

  assert.deepEqual(media, [])
})

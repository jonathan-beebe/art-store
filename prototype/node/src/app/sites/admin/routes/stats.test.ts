import { test } from 'node:test'
import assert from 'node:assert/strict'
import { recordPageView } from '../../../actions/analytics/record-page-view.ts'
import { toggleFavorite } from '../../../actions/favorites/toggle-favorite.ts'
import { recordListingEvent } from '../../../actions/listings/record-listing-event.ts'
import { buildTestApp, signInAsAdmin } from '../../../test/build-test-app.ts'
import { createCustomer, createListing, createSeller } from '../../../test/commerce-world.ts'

test('the site stats page shows page views by day and by path pattern', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const context = { db: testApp.db, clock: testApp.clock }
  await recordPageView(context, { site: 'shop', pathPattern: '/art/:slug' })
  await recordPageView(context, { site: 'shop', pathPattern: '/art/:slug' })
  await recordPageView(context, { site: 'seller', pathPattern: '/seller' })

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/stats',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /<title>Site stats — Admin<\/title>/)
  assert.match(response.body, /data-day="2026-08-24"[\s\S]*?data-cell="count"[^>]*>3</)
  assert.match(response.body, /data-pattern="shop \/art\/:slug"[\s\S]*?data-cell="count"[^>]*>2</)
  assert.match(response.body, /data-pattern="seller \/seller"[\s\S]*?data-cell="count"[^>]*>1</)
})

test('the site stats page counts listing events by type', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const listing = await createListing(context, sellerId)
  const customerId = await createCustomer(context)

  await recordListingEvent(context, { listingId: listing.id, customerId, eventType: 'view' })
  await toggleFavorite(context, { customerId, listingId: listing.id })

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/stats',
    cookies: admin.cookies,
  })

  assert.match(response.body, /data-stat="event-view"[\s\S]*?>1</)
  assert.match(response.body, /data-stat="event-favorite"[\s\S]*?>1</)
  assert.match(response.body, /data-stat="event-cart_add"[\s\S]*?>0</)
})

test('an empty platform says so rather than showing an empty table', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/stats',
    cookies: admin.cookies,
  })

  assert.match(response.body, /No page views recorded yet\./)
})

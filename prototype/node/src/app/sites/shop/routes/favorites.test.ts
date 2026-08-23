import { test } from 'node:test'
import assert from 'node:assert/strict'
import { changeListingStatus } from '../../../actions/listings/change-listing-status.ts'
import { browseAsAnonymousCustomer, buildTestApp, signInAsAdmin, signInAsSeller } from '../../../test/build-test-app.ts'
import { listArtwork, removeListing } from '../storefront-fixtures.ts'

test('saving a piece puts it on the favorites page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  await testApp.app.inject({
    method: 'POST',
    url: '/art/harbour-at-dusk/favorite',
    cookies: customer.cookies,
  })
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/favorites',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /<title>Favorites — Art Store<\/title>/)
  assert.match(response.body, /Harbour at dusk/)
  assert.match(response.body, /ada/)
})

test('toggling a saved piece off removes it from favorites', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  await testApp.app.inject({
    method: 'POST',
    url: '/art/harbour-at-dusk/favorite',
    cookies: customer.cookies,
  })
  await testApp.app.inject({
    method: 'POST',
    url: '/art/harbour-at-dusk/favorite',
    cookies: customer.cookies,
  })
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/favorites',
    cookies: customer.cookies,
  })

  assert.doesNotMatch(response.body, /Harbour at dusk/)
})

test('favoriting redirects back to where the visitor was', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/art/harbour-at-dusk/favorite',
    cookies: customer.cookies,
    headers: { referer: '/?q=harbour' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/?q=harbour')
})

test('favoriting with nowhere to go back to lands on the listing page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/art/harbour-at-dusk/favorite',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/art/harbour-at-dusk')
})

test('the empty state says nothing is saved yet', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await browseAsAnonymousCustomer(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/favorites',
    cookies: customer.cookies,
  })

  assert.match(response.body, /Nothing saved yet\. Tap Favorite on any piece you want to come back to\./)
})

test('favoriting a listing that is not on the storefront answers 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Unfinished sketch', status: 'draft' })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/art/unfinished-sketch/favorite',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('favoriting a removed listing answers 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Taken down' })
  await removeListing(testApp, { listingId: listing.id, adminId: admin.id })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/art/taken-down/favorite',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('a favorited listing that later sold still shows, marked Sold', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Last copy' })
  await testApp.app.inject({
    method: 'POST',
    url: '/art/last-copy/favorite',
    cookies: customer.cookies,
  })

  await changeListingStatus(testApp, { listingId: listing.id, status: 'sold' })
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/favorites',
    cookies: customer.cookies,
  })

  assert.match(response.body, /Last copy/)
  assert.match(response.body, /Sold/)
})

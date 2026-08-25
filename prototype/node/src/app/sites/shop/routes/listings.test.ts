import { test } from 'node:test'
import assert from 'node:assert/strict'
import { publishListingFaq } from '../../../actions/messaging/publish-listing-faq.ts'
import { buildLoggedTestApp } from '../../../test/log-lines.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsSeller,
} from '../../../test/build-test-app.ts'
import { blockCustomer, listArtwork, removeListing } from '../storefront-fixtures.ts'
import { cents } from '../../../core/money.ts'

test('a listing page shows the piece, the shop, the price, and the description', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  await listArtwork(testApp, {
    sellerId: seller.id,
    title: 'Harbour at dusk',
    description: 'Oil study of the harbour.',
    priceCents: cents(24_000),
  })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/harbour-at-dusk' })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /<title>Harbour at dusk — Art Store<\/title>/)
  assert.match(response.body, /Oil study of the harbour\./)
  assert.match(response.body, /\$240\.00/)
  assert.match(response.body, /ada/)
  assert.match(response.body, /src="\/placeholders\/Harbour%20at%20dusk"/)
  assert.doesNotMatch(response.body, /data:image/)
  assert.match(response.body, /action="\/cart\/harbour-at-dusk"/)
  assert.match(response.body, /Favorite/)
})

test('a listing page carries an empty questions section until one is answered', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/harbour-at-dusk' })

  assert.match(response.body, /Questions/)
  assert.match(response.body, /No questions yet\./)
})

test('a published FAQ appears on the listing page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await publishListingFaq(testApp, {
    listingId: listing.id,
    draft: { question: 'Is this framed?', answer: 'No, it ships unframed.' },
  })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/harbour-at-dusk' })

  assert.match(response.body, /Is this framed\?/)
  assert.match(response.body, /No, it ships unframed\./)
})

test('the listing page carries a form to ask the seller a question', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/harbour-at-dusk' })

  assert.match(response.body, /action="\/art\/harbour-at-dusk\/questions"/)
  assert.match(response.body, /name="body"/)
})

test('looking at a listing records a view against it', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  await testApp.app.inject({
    method: 'GET',
    url: '/art/harbour-at-dusk',
    cookies: customer.cookies,
  })

  const events = await testApp.db
    .selectFrom('listingEvents')
    .selectAll()
    .where('listingId', '=', listing.id)
    .execute()

  assert.equal(events.length, 1)
  assert.equal(events[0]?.eventType, 'view')
  assert.equal(events[0]?.customerId, customer.id)

  const did = testApp.logLines.line('listing.view', 'did')
  assert.equal(did.level, 'debug')
  assert.equal((did.data as { listing_id?: string }).listing_id, listing.id)
})

test('a second look inside the hour is refused rather than counted again', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const look = { method: 'GET' as const, url: '/art/harbour-at-dusk', cookies: customer.cookies }
  await testApp.app.inject(look)
  await testApp.app.inject(look)

  const refused = testApp.logLines.line('listing.view', 'refused')
  assert.equal(refused.level, 'debug')
  assert.equal(refused.msg, 'this customer already viewed the listing this hour')
})

test('a sold listing keeps its page and says it is sold out', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Last copy', status: 'sold' })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/last-copy' })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /Sold out/)
  assert.doesNotMatch(response.body, /action="\/cart\/last-copy"/)
})

test('a draft listing is not on the storefront', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Unfinished sketch', status: 'draft' })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/unfinished-sketch' })

  assert.equal(response.statusCode, 404)
  assert.match(response.body, /Nothing here/)
})

test('an archived listing is not on the storefront', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Retired print', status: 'archived' })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/retired-print' })

  assert.equal(response.statusCode, 404)
})

test('a removed listing leaves the storefront whatever its status', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Taken down' })
  await removeListing(testApp, { listingId: listing.id, adminId: admin.id })

  const response = await testApp.app.inject({ method: 'GET', url: '/art/taken-down' })

  assert.equal(response.statusCode, 404)
})

test('a slug that names nothing is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/art/nothing-here' })

  assert.equal(response.statusCode, 404)
})

test('a blocked customer is told why the piece has no add-to-cart button', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await blockCustomer(testApp, { customerId: customer.id, adminId: admin.id })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/art/harbour-at-dusk',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-blocked/)
  assert.match(response.body, /Chargeback fraud\./)
  assert.doesNotMatch(response.body, /action="\/cart\/harbour-at-dusk"/)
})

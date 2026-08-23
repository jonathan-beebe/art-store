import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { LightMyRequestResponse } from 'fastify'
import { buildTestApp, signInAsAdmin, signInAsSeller } from '../../../test/build-test-app.ts'
import { MAX_IMAGE_UPLOAD_BYTES } from '../listing-image-upload.ts'
import { createForSaleListing, createFulfillment, createTestListing } from '../test-fixtures.ts'
import { cents } from '../../../core/money.ts'

const PNG_SIGNATURE = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])

/** The signed flash cookie a redirect response set, for a follow-up request
 * that expects to read the message it carried. */
function flashCookie(response: LightMyRequestResponse): Record<string, string> {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')

  return cookie === undefined ? {} : { flash: String(cookie.value) }
}

function submittedFields(overrides: Record<string, string> = {}): Record<string, string> {
  return {
    title: 'Harbour at Dusk',
    description: 'Oil on canvas.',
    medium: 'Oil',
    dimensions: '40 x 60 cm',
    price: '249.00',
    quantity: '2',
    ...overrides,
  }
}

function multipartPayload(fields: Record<string, string>, boundary = '----listingtest'): Buffer {
  const parts = Object.entries(fields).flatMap(([name, value]) => [
    `--${boundary}`,
    `Content-Disposition: form-data; name="${name}"`,
    '',
    value,
  ])
  parts.push(`--${boundary}--`, '')

  return Buffer.from(parts.join('\r\n'))
}

function multipartHeaders(boundary = '----listingtest'): Record<string, string> {
  return { 'content-type': `multipart/form-data; boundary=${boundary}` }
}

/** A multipart body carrying the listing's text fields plus one uploaded
 * "image" part — filename, Content-Type, and bytes set independently, so a
 * test can claim one thing in the headers and send different bytes. */
function payloadWithImage(
  fields: Record<string, string>,
  image: { filename: string; contentType: string; bytes: Buffer },
  boundary = '----listingtest',
): Buffer {
  const textParts = Object.entries(fields).flatMap(([name, value]) => [
    `--${boundary}`,
    `Content-Disposition: form-data; name="${name}"`,
    '',
    value,
  ])
  const preamble = Buffer.from(
    [
      ...textParts,
      `--${boundary}`,
      `Content-Disposition: form-data; name="image"; filename="${image.filename}"`,
      `Content-Type: ${image.contentType}`,
      '',
      '',
    ].join('\r\n'),
  )

  return Buffer.concat([preamble, image.bytes, Buffer.from(`\r\n--${boundary}--\r\n`)])
}

test('a signed-out visitor reaches no listing page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const listing = await createTestListing(testApp, rival.id)

  for (const url of ['/seller/listings', '/seller/listings/new', `/seller/listings/${listing.id}/edit`]) {
    const response = await testApp.app.inject({ method: 'GET', url })
    assert.equal(response.statusCode, 302, url)
  }

  const created = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    headers: multipartHeaders(),
    payload: multipartPayload(submittedFields()),
  })
  assert.equal(created.statusCode, 302)
})

test("the index lists the seller's own listings with their activity", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id, {
    title: 'Harbour at Dusk',
    priceCents: cents(45_000),
    quantity: 2,
  })
  await testApp.db
    .insertInto('listingEvents')
    .values([
      { listingId: listing.id, customerId: null, eventType: 'view', occurredAt: new Date().toISOString() },
      { listingId: listing.id, customerId: null, eventType: 'cart_add', occurredAt: new Date().toISOString() },
    ])
    .execute()

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/listings', cookies: seller.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-cell="status"[^>]*>Draft</)
  assert.match(response.body, /\$450\.00/)
  assert.match(response.body, /Harbour at Dusk/)
  assert.match(response.body, /data-activity="views"[^>]*>\s*1\s*</)
  assert.match(response.body, /data-activity="cart_adds"[^>]*>\s*1\s*</)
})

test("another seller's listings stay off the index", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createTestListing(testApp, rival.id, { title: 'Rival Work' })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/listings', cookies: seller.cookies })

  assert.doesNotMatch(response.body, new RegExp(`data-listing="${rivalListing.id}"`))
  assert.match(response.body, /No listings yet/)
})

test('the index offers only the transitions the lifecycle allows', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createTestListing(testApp, seller.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/listings', cookies: seller.cookies })

  assert.match(response.body, /data-status-button="for_sale"/)
  assert.match(response.body, /data-status-button="archived"/)
  assert.doesNotMatch(response.body, /data-status-button="sold"/)
})

test('the new listing form asks for every field', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/listings/new', cookies: seller.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /for="listing_title"/)
  assert.match(response.body, /for="listing_price"/)
  assert.match(response.body, /for="listing_image"/)
  assert.match(response.body, /type="file"[^>]*name="image"/)
})

test('creating a listing stores it as a draft priced in cents', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(submittedFields()),
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/seller/listings')
  const listing = await testApp.db.selectFrom('listings').selectAll().where('sellerId', '=', seller.id).executeTakeFirstOrThrow()
  assert.equal(listing.title, 'Harbour at Dusk')
  assert.equal(listing.priceCents, 24_900)
  assert.equal(listing.status, 'draft')
  assert.equal(listing.slug, 'harbour-at-dusk')
})

test('a price that is not an amount in dollars is refused', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(submittedFields({ price: 'free' })),
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="listing_price"/)
  assert.match(response.body, /amount in dollars/)
  assert.equal((await testApp.db.selectFrom('listings').selectAll().execute()).length, 0)
})

test('a listing with no title is refused', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(submittedFields({ title: ' ' })),
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="listing_title"[^>]*>Enter a title\./)
})

test('creating a listing attaches an uploaded image whose bytes sniff as a real PNG', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const payload = payloadWithImage(submittedFields(), {
    filename: 'harbour.png',
    contentType: 'image/png',
    bytes: Buffer.concat([PNG_SIGNATURE, Buffer.from('rest-of-file')]),
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload,
  })

  assert.equal(response.statusCode, 302)
  const listing = await testApp.db.selectFrom('listings').selectAll().where('sellerId', '=', seller.id).executeTakeFirstOrThrow()
  assert.match(listing.imagePath ?? '', /^\/uploads\/.+\.png$/)

  const served = await testApp.app.inject({ method: 'GET', url: listing.imagePath ?? '' })
  assert.equal(served.statusCode, 200)
  assert.equal(served.headers['x-content-type-options'], 'nosniff')
})

test('an upload that is not an image is refused', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const payload = payloadWithImage(submittedFields(), {
    filename: 'doc.pdf',
    contentType: 'application/pdf',
    bytes: Buffer.from('%PDF-1.4'),
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="listing_image"[^>]*>Upload an image file\./)
  assert.equal((await testApp.db.selectFrom('listings').selectAll().execute()).length, 0)
})

test('a filename and Content-Type claiming to be an image are not trusted — only the bytes are', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const payload = payloadWithImage(submittedFields(), {
    filename: 'evil.html',
    contentType: 'image/anything',
    bytes: Buffer.from('<script>alert(document.cookie)</script>'),
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="listing_image"[^>]*>Upload an image file\./)
  assert.equal((await testApp.db.selectFrom('listings').selectAll().execute()).length, 0)
})

test('an SVG upload is refused — it is not a magic-byte image format', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const payload = payloadWithImage(submittedFields(), {
    filename: 'evil.svg',
    contentType: 'image/svg+xml',
    bytes: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="listing_image"[^>]*>Upload an image file\./)
  assert.equal((await testApp.db.selectFrom('listings').selectAll().execute()).length, 0)
})

test('an image over the size limit re-renders the form with a field error instead of a JSON 413', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const oversized = Buffer.concat([PNG_SIGNATURE, Buffer.alloc(MAX_IMAGE_UPLOAD_BYTES, 1)])
  const payload = payloadWithImage(submittedFields(), {
    filename: 'huge.png',
    contentType: 'image/png',
    bytes: oversized,
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.headers['content-type'] ?? '', /text\/html/)
  assert.match(response.body, /data-field-error="listing_image"[^>]*>Upload an image under 5 MB\./)
  assert.equal((await testApp.db.selectFrom('listings').selectAll().execute()).length, 0)
})

test('an oversized image on an edit form re-renders that listing, not a blank one', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id, { title: 'Harbour at Dusk' })
  const oversized = Buffer.concat([PNG_SIGNATURE, Buffer.alloc(MAX_IMAGE_UPLOAD_BYTES, 1)])
  const payload = payloadWithImage(submittedFields({ title: 'Harbour at Dusk' }), {
    filename: 'huge.png',
    contentType: 'image/png',
    bytes: oversized,
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /value="Harbour at Dusk"/)
  assert.match(response.body, /data-field-error="listing_image"[^>]*>Upload an image under 5 MB\./)
  const unchanged = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.title, 'Harbour at Dusk')
})

test('a listing id that is not a number is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/listings/not-a-number/edit',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('the edit form is filled with the listing as it stands', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id, {
    title: 'Harbour at Dusk',
    priceCents: cents(45_000),
    quantity: 3,
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${listing.id}/edit`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /value="Harbour at Dusk"/)
  assert.match(response.body, /value="450\.00"/)
  assert.match(response.body, /id="listing_quantity"\s+value="3"/)
})

test("editing another seller's listing is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createTestListing(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${rivalListing.id}/edit`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('updating a listing writes the edited fields', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(submittedFields({ title: 'Harbour at Dawn' })),
  })

  assert.equal(response.statusCode, 302)
  const updated = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(updated.title, 'Harbour at Dawn')
  assert.equal(updated.priceCents, 24_900)
})

test('an edit that fails validation leaves the listing alone', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id, { title: 'Harbour at Dusk' })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(submittedFields({ quantity: '1000' })),
  })

  assert.equal(response.statusCode, 422)
  const unchanged = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.title, 'Harbour at Dusk')
})

test("updating another seller's listing is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createTestListing(testApp, rival.id, { title: 'Rival Work' })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${rivalListing.id}`,
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(submittedFields()),
  })

  assert.equal(response.statusCode, 404)
  const unchanged = await testApp.db.selectFrom('listings').selectAll().where('id', '=', rivalListing.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.title, 'Rival Work')
})

test('a draft the lifecycle allows to go on sale goes on sale', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/status`,
    cookies: seller.cookies,
    payload: { status: 'for_sale' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/seller/listings')
  const updated = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(updated.status, 'for_sale')
  const follow = await testApp.app.inject({
    method: 'GET',
    url: '/seller/listings',
    cookies: { ...seller.cookies, ...flashCookie(response) },
  })
  assert.match(follow.body, /is now for sale/)
})

test('a move the lifecycle refuses leaves the listing alone and says why', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/status`,
    cookies: seller.cookies,
    payload: { status: 'sold' },
  })

  assert.equal(response.statusCode, 302)
  const unchanged = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'draft')
  const follow = await testApp.app.inject({
    method: 'GET',
    url: '/seller/listings',
    cookies: { ...seller.cookies, ...flashCookie(response) },
  })
  assert.match(follow.body, /A listing cannot move from draft to sold\./)
})

test('a status nothing in the lifecycle names is refused', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/status`,
    cookies: seller.cookies,
    payload: { status: 'on_loan' },
  })

  assert.equal(response.statusCode, 302)
  const unchanged = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'draft')
})

test("changing another seller's listing status is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createTestListing(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${rivalListing.id}/status`,
    cookies: seller.cookies,
    payload: { status: 'for_sale' },
  })

  assert.equal(response.statusCode, 404)
})

test('a removed listing shows the removal kind and reason and cannot be put back on sale', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  await testApp.db
    .insertInto('listingRemovals')
    .values({
      listingId: listing.id,
      adminId: admin.id,
      kind: 'temporary',
      reason: 'Reported as a duplicate listing.',
      createdAt: new Date().toISOString(),
      liftedAt: null,
    })
    .execute()

  const show = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
  })

  assert.match(show.body, /data-removal-kind="temporary"/)
  assert.match(show.body, /Reported as a duplicate listing\./)
  assert.doesNotMatch(show.body, /data-status-button="for_sale"/)

  const changeStatus = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/status`,
    cookies: seller.cookies,
    payload: { status: 'for_sale' },
  })

  assert.equal(changeStatus.statusCode, 302)
  const unchanged = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'for_sale')
  const follow = await testApp.app.inject({
    method: 'GET',
    url: '/seller/listings',
    cookies: { ...seller.cookies, ...flashCookie(changeStatus) },
  })
  assert.match(follow.body, /removed by an admin and cannot be put back on sale/)
})

test('a removed listing cannot go back on sale even through a transition the lifecycle table allows', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  await testApp.db.updateTable('listings').set({ status: 'sold' }).where('id', '=', listing.id).execute()
  await testApp.db
    .insertInto('listingRemovals')
    .values({
      listingId: listing.id,
      adminId: admin.id,
      kind: 'permanent',
      reason: 'Reported as counterfeit.',
      createdAt: new Date().toISOString(),
      liftedAt: null,
    })
    .execute()

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/status`,
    cookies: seller.cookies,
    payload: { status: 'for_sale' },
  })

  assert.equal(response.statusCode, 302)
  const unchanged = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'sold')
  const follow = await testApp.app.inject({
    method: 'GET',
    url: '/seller/listings',
    cookies: { ...seller.cookies, ...flashCookie(response) },
  })
  assert.match(follow.body, /removed by an admin and cannot be put back on sale/)
})

test("the activity page totals the events of the seller's own listing", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id)
  await testApp.db
    .insertInto('listingEvents')
    .values([
      { listingId: listing.id, customerId: null, eventType: 'view', occurredAt: new Date().toISOString() },
      { listingId: listing.id, customerId: null, eventType: 'view', occurredAt: new Date().toISOString() },
      { listingId: listing.id, customerId: null, eventType: 'favorite', occurredAt: new Date().toISOString() },
    ])
    .execute()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-stat="views"[^>]*>\s*2\s*</)
  assert.match(response.body, /data-stat="favorites"[^>]*>\s*1\s*</)
  assert.match(response.body, /data-stat="cart_adds"[^>]*>\s*0\s*</)
})

test('the activity page breaks the last fortnight down by day', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
  })

  assert.equal((response.body.match(/data-day="/g) ?? []).length, 14)
})

test('the activity page lists the orders that bought the listing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)
  const listing = await testApp.db
    .selectFrom('orderItems')
    .selectAll()
    .where('orderId', '=', fulfillment.orderId)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${listing.listingId}`,
    cookies: seller.cookies,
  })

  assert.match(response.body, new RegExp(`data-sale="${fulfillment.orderId}"`))
  assert.match(response.body, /data-cell="order_status"[^>]*>Paid</)
})

test('a listing with no sales says so', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createTestListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
  })

  assert.match(response.body, /No sales yet\./)
})

test("another seller's activity page is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createTestListing(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${rivalListing.id}`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

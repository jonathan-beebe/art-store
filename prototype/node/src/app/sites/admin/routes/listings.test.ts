import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin } from '../../../test/build-test-app.ts'
import { createListing, createSeller } from '../../../test/commerce-world.ts'
import { fixtureId } from '../../../test/fixture-ids.ts'

test('GET /admin/listings lists every listing with its seller, price, and storefront status', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock }, 'Blue Kiln Studio')
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, {
    title: 'Harbour at Dusk',
    priceCents: 45_000,
    quantity: 2,
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/listings',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-listing="${listing.id}"`))
  assert.match(response.body, /Blue Kiln Studio/)
  assert.match(response.body, /\$450\.00/)
  assert.match(response.body, /data-cell="on-storefront"[^]*?Yes/)
})

test('GET /admin/listings filters by status', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, { title: 'Draft one', status: 'draft' })
  const forSale = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, {
    title: 'For sale one',
    status: 'for_sale',
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/listings?status=for_sale',
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-listing="${forSale.id}"`))
  assert.doesNotMatch(response.body, /Draft one/)
})

test('GET /admin/listings filters by seller', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const first = await createSeller({ db: testApp.db, clock: testApp.clock })
  const second = await createSeller({ db: testApp.db, clock: testApp.clock })
  await createListing({ db: testApp.db, clock: testApp.clock }, first, { title: 'Not this one' })
  const wanted = await createListing({ db: testApp.db, clock: testApp.clock }, second, { title: 'This one' })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/listings?seller=${second}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-listing="${wanted.id}"`))
  assert.doesNotMatch(response.body, /Not this one/)
})

test('the filter form remembers the submitted values', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = fixtureId('sel', 3)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/listings?status=for_sale&seller=${sellerId}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, /<option value="for_sale" selected>/)
  assert.match(response.body, new RegExp(`value="${sellerId}"`))
})

test('GET /admin/listings defaults to showing removed and visible listings, and can filter either', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const visible = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, { title: 'Visible one' })
  const removed = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, { title: 'Removed one' })

  await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${removed.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'temporary', reason: 'Reported.', redirect_to: '/admin/listings' },
  })

  const any = await testApp.app.inject({ method: 'GET', url: '/admin/listings', cookies: admin.cookies })
  assert.match(any.body, new RegExp(`data-listing="${visible.id}"`))
  assert.match(any.body, new RegExp(`data-listing="${removed.id}"`))

  const removedOnly = await testApp.app.inject({
    method: 'GET',
    url: '/admin/listings?removed=removed',
    cookies: admin.cookies,
  })
  assert.doesNotMatch(removedOnly.body, /Visible one/)
  assert.match(removedOnly.body, /Removed one/)

  const visibleOnly = await testApp.app.inject({
    method: 'GET',
    url: '/admin/listings?removed=visible',
    cookies: admin.cookies,
  })
  assert.match(visibleOnly.body, /Visible one/)
  assert.doesNotMatch(visibleOnly.body, /Removed one/)
})

test('a full page of listings shows 25 and a link to the next page, which shows the rest', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)

  for (let i = 0; i < 27; i += 1) {
    await createListing(context, sellerId, { title: `Piece ${i}` })
  }

  const firstPage = await testApp.app.inject({ method: 'GET', url: '/admin/listings', cookies: admin.cookies })
  assert.equal((firstPage.body.match(/data-listing="/g) ?? []).length, 25)
  assert.match(firstPage.body, /page=2/)

  const secondPage = await testApp.app.inject({
    method: 'GET',
    url: '/admin/listings?page=2',
    cookies: admin.cookies,
  })
  assert.equal((secondPage.body.match(/data-listing="/g) ?? []).length, 2)
})

test('GET /admin/listings/:id shows the listing, its seller, and its removal history', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock }, 'Blue Kiln Studio')
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, {
    title: 'Harbour at Dusk',
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/listings/${listing.id}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-listing="${listing.id}"`))
  assert.match(response.body, /Harbour at Dusk/)
  assert.match(response.body, /Blue Kiln Studio/)
  assert.match(response.body, /No removals on record\./)
})

test('GET /admin/listings/:id answers 404 for an id that names nobody', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/listings/999999',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('the removal and lift forms are wired to the exact contract paths and field names', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/listings/${listing.id}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`action="/admin/listings/${listing.id}/removals"`))
  assert.match(response.body, /name="kind"/)
  assert.match(response.body, /name="reason"/)
  assert.match(response.body, /name="redirect_to"/)
})

test('the "all" options submit empty filters, which the table reads as no filter', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/listings?status=&seller=&removed=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
})

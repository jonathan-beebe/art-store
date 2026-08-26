import { test } from 'node:test'
import assert from 'node:assert/strict'
import { removeListing } from '../../../actions/moderation/remove-listing.ts'
import { mustSucceed } from '../../../core/refusal.ts'
import {
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
} from '../../../test/build-test-app.ts'
import { createAdmin, createCustomer, createListing, createSeller, paidOrder } from '../../../test/commerce-world.ts'

test('the sellers list renders for a signed-in admin, one row per seller', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const sellerId = await createSeller(context, 'Blue Kiln Studio')
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId, { priceCents: 45_000 })
  await paidOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/sellers',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-seller="${sellerId}"`))
  assert.match(response.body, /Blue Kiln Studio/)
  assert.match(response.body, /data-cell="listings"[^]*?>1</)
  assert.match(response.body, /data-cell="fulfillments"[^]*?>1</)
  assert.match(response.body, /data-cell="held"[^]*?\$405\.00</)
})

test('a visitor with no admin cookie is sent to sign in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/sellers' })

  assert.equal(response.statusCode, 302)
})

test('a seller cookie does not open the admin sellers page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/sellers',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 302)
})

test('a customer cookie does not open the admin sellers page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/sellers',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
})

test('the seller detail page shows listings, an active removal, fulfillments, and the balance', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const sellerId = await createSeller(context, 'Blue Kiln Studio')
  const customerId = await createCustomer(context)
  const adminId = await createAdmin(context)
  const contestedListing = await createListing(context, sellerId, { title: 'Contested Piece' })
  const okListing = await createListing(context, sellerId, { title: 'Harbour at Dusk', priceCents: 45_000 })

  mustSucceed(
    await removeListing(context, {
      listingId: contestedListing.id,
      adminId,
      kind: 'temporary',
      reason: 'Reported as counterfeit.',
    }),
  )
  await paidOrder(context, customerId, [okListing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/sellers/${sellerId}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /Blue Kiln Studio/)
  assert.match(response.body, new RegExp(`data-listing="${contestedListing.id}"[^]*?Reported as counterfeit\\.`))
  assert.match(response.body, new RegExp(`data-listing="${okListing.id}"`))
  assert.match(response.body, /data-cell="net"[^]*?\$405\.00</)
  assert.match(response.body, /href="\/admin\/listings\/lst_[0-9A-HJKMNP-TV-Z]{26}"/)
})

test('the seller detail page titles a blank shop name by the address before the @, not the whole address', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const sellerId = await createSeller(context, '   ')
  const seller = await testApp.db
    .selectFrom('sellers')
    .selectAll()
    .where('id', '=', sellerId)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/sellers/${sellerId}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`<title>${seller.email.split('@')[0]} — Admin</title>`))
  assert.doesNotMatch(response.body, new RegExp(`<title>${seller.email} — Admin</title>`))
})

test('a seller id that names nobody is 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/sellers/999999',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('a non-numeric seller id is 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/sellers/not-a-number',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

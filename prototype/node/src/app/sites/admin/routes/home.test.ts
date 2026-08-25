import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  type TestApp,
} from '../../../test/build-test-app.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  paidOrder,
} from '../../../test/commerce-world.ts'

function contextOf({ db, clock }: TestApp): { db: TestApp['db']; clock: TestApp['clock'] } {
  return { db, clock }
}

test('the admin home page renders in the admin layout', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({ method: 'GET', url: '/admin', cookies: admin.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.headers['content-type'] ?? '', /text\/html/)
  assert.match(response.body, /<title>Overview — Admin<\/title>/)
  assert.match(response.body, /href="\/app(\.[0-9a-f]{8})?\.css"/)
})

test('a visitor with no admin cookie is sent to the admin sign-in page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/admin' })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/admin/login?redirect_to=%2Fadmin')
})

test('a seller cookie and a customer cookie do not open the admin site', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)

  const asSeller = await testApp.app.inject({
    method: 'GET',
    url: '/admin',
    cookies: seller.cookies,
  })
  const asCustomer = await testApp.app.inject({
    method: 'GET',
    url: '/admin',
    cookies: customer.cookies,
  })

  assert.equal(asSeller.statusCode, 302)
  assert.equal(asCustomer.statusCode, 302)
})

test('the dashboard counts people, listings, orders, fulfillments, and money', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const context = contextOf(testApp)
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId, { priceCents: 45_000 })
  await createListing(context, sellerId, { status: 'draft' })
  await paidOrder(context, customerId, [listing.id])

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({ method: 'GET', url: '/admin', cookies: admin.cookies })
  const body = response.body

  assert.match(body, /data-stat="sellers"[\s\S]*?>1</)
  assert.match(body, /data-stat="verified-customers"[\s\S]*?>1</)
  assert.match(body, /data-stat="anonymous-customers"[\s\S]*?>1</)
  assert.match(body, /data-stat="listing-draft"[\s\S]*?>1</)
  assert.match(body, /data-stat="listing-sold"[\s\S]*?>1</)
  assert.match(body, /data-stat="order-paid"[\s\S]*?>1</)
  assert.match(body, /data-stat="fulfillment-awaiting_shipment"[\s\S]*?>1</)
  assert.match(body, /data-stat="held"[\s\S]*?>\$405\.00</)
  assert.match(body, /data-stat="fees-earned"[\s\S]*?>\$45\.00</)
  assert.match(body, /data-stat="paid-out"[\s\S]*?>\$0\.00</)
})

test('the dashboard shows the page views the rollup hook recorded', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)

  await testApp.app.inject({ method: 'GET', url: '/admin', cookies: admin.cookies })

  // The count a page shows is what was rolled up before it rendered, so the
  // first visit is the one the second visit reports.
  for (let turn = 0; turn < 20; turn += 1) await new Promise((r) => setImmediate(r))

  const second = await testApp.app.inject({
    method: 'GET',
    url: '/admin',
    cookies: admin.cookies,
  })

  assert.match(second.body, /data-stat="views-today"[\s\S]*?>1</)
  assert.match(second.body, /data-stat="views-this-week"[\s\S]*?>1</)
})

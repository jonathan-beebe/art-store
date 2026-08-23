import { test } from 'node:test'
import assert from 'node:assert/strict'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { cents } from '../../../core/money.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsCustomer,
  signInAsSeller,
  type TestApp,
} from '../../../test/build-test-app.ts'
import {
  cartWithArtwork,
  listArtwork,
  payForOrder,
  placeCustomerOrder,
} from '../storefront-fixtures.ts'

async function orderOneArtwork(
  testApp: TestApp,
  input: { customerId: number; sellerEmail?: string; title?: string; isEmailVerified?: boolean },
) {
  const seller = await signInAsSeller(testApp, input.sellerEmail ?? 'ada@example.test')
  const listing = await listArtwork(testApp, {
    sellerId: seller.id,
    title: input.title ?? 'Harbour at dusk',
    priceCents: cents(24_000),
  })
  const cartId = await cartWithArtwork(testApp, {
    customerId: input.customerId,
    listingId: listing.id,
  })
  const order = await placeCustomerOrder(testApp, {
    cartId,
    customerId: input.customerId,
    isEmailVerified: input.isEmailVerified ?? true,
  })

  return { seller, listing, order }
}

test('the orders page lists what a customer has bought, newest first', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const first = await orderOneArtwork(testApp, { customerId: customer.id, title: 'Harbour at dusk' })
  const second = await orderOneArtwork(testApp, { customerId: customer.id, title: 'Kiln study' })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/orders',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`Order #${second.order.id}`))
  assert.match(response.body, new RegExp(`Order #${first.order.id}`))
  assert.match(response.body, /Harbour at dusk/)
  assert.match(response.body, /Kiln study/)
  assert.match(response.body, /\$240\.00/)
  assert.match(response.body, /24 August 2026/)
  assert.ok(
    response.body.indexOf(`Order #${second.order.id}`) <
      response.body.indexOf(`Order #${first.order.id}`),
  )
})

test('a customer who has bought nothing is told so', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/orders',
    cookies: customer.cookies,
  })

  assert.match(response.body, /No orders yet\./)
})

test('an order page shows the shipping address and one section per seller', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { order } = await orderOneArtwork(testApp, { customerId: customer.id })
  await payForOrder(testApp, { orderId: order.id })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`Order #${order.id}`))
  assert.match(response.body, /data-order-status[^>]*>\s*Paid/)
  assert.match(response.body, /ada/)
  assert.match(response.body, /Awaiting shipment/)
  assert.match(response.body, /Harbour at dusk/)
  assert.match(response.body, /12 Analytical Way/)
  assert.match(response.body, /EC1A 1BB/)
})

test('an order spanning two sellers shows a section for each', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const ada = await signInAsSeller(testApp, 'ada@example.test')
  const kiln = await signInAsSeller(testApp, 'kiln@example.test')
  const first = await listArtwork(testApp, { sellerId: ada.id, title: 'Harbour at dusk' })
  const second = await listArtwork(testApp, { sellerId: kiln.id, title: 'Kiln study' })
  const cartId = await cartWithArtwork(testApp, {
    customerId: customer.id,
    listingId: first.id,
  })
  await cartWithArtwork(testApp, { customerId: customer.id, listingId: second.id })
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })
  await payForOrder(testApp, { orderId: order.id })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })

  assert.equal(response.body.match(/data-fulfillment /g)?.length, 2)
  assert.match(response.body, /ada/)
  assert.match(response.body, /kiln/)
})

test('carrier and tracking appear once the seller ships', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { order } = await orderOneArtwork(testApp, { customerId: customer.id })
  await payForOrder(testApp, { orderId: order.id })

  const beforeShipping = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })
  assert.doesNotMatch(beforeShipping.body, /data-tracking/)
  assert.doesNotMatch(beforeShipping.body, /Confirm delivery/)

  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()
  await markShipped(testApp, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123456789GB',
  })

  const afterShipping = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })
  assert.match(afterShipping.body, /data-tracking/)
  assert.match(afterShipping.body, /Royal Mail/)
  assert.match(afterShipping.body, /RM123456789GB/)
  assert.match(afterShipping.body, /Confirm delivery/)
})

test('a guest order tells the buyer to open the emailed link', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const guest = await browseAsAnonymousCustomer(testApp)
  const { order } = await orderOneArtwork(testApp, {
    customerId: guest.id,
    isEmailVerified: false,
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: guest.cookies,
  })

  assert.equal(order.status, 'pending_verification')
  assert.match(response.body, /data-verification-notice/)
  assert.match(response.body, /Check your email/)
  assert.doesNotMatch(response.body, /name="card_number"/)
})

test('cancelling an unpaid order hands its stock back to the storefront', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { order, listing } = await orderOneArtwork(testApp, { customerId: customer.id })

  const page = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })
  assert.match(page.body, /Cancel this order/)

  const cancelled = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/cancel`,
    cookies: customer.cookies,
  })

  assert.equal(cancelled.statusCode, 302)
  assert.equal(cancelled.headers.location, `/orders/${order.id}`)

  const row = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', order.id)
    .executeTakeFirstOrThrow()
  assert.equal(row.status, 'cancelled')

  const stock = await testApp.db
    .selectFrom('listings')
    .selectAll()
    .where('id', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.equal(stock.status, 'for_sale')
  assert.equal(stock.quantity, 1)
})

test('a paid order can no longer be cancelled', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { order } = await orderOneArtwork(testApp, { customerId: customer.id })
  await payForOrder(testApp, { orderId: order.id })

  const page = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })
  assert.doesNotMatch(page.body, /Cancel this order/)

  const cancelled = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/cancel`,
    cookies: customer.cookies,
  })

  assert.equal(cancelled.statusCode, 404)
})

test("someone else's order is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const buyer = await signInAsCustomer(testApp, 'buyer@example.com')
  const stranger = await signInAsCustomer(testApp, 'stranger@example.com')
  const { order } = await orderOneArtwork(testApp, { customerId: buyer.id })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: stranger.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('an order id that names nothing is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)

  const missing = await testApp.app.inject({
    method: 'GET',
    url: '/orders/404',
    cookies: customer.cookies,
  })
  const nonsense = await testApp.app.inject({
    method: 'GET',
    url: '/orders/not-a-number',
    cookies: customer.cookies,
  })

  assert.equal(missing.statusCode, 404)
  assert.equal(nonsense.statusCode, 404)
})

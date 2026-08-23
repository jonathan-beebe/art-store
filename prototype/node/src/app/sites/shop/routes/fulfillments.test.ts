import { test } from 'node:test'
import assert from 'node:assert/strict'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { cents } from '../../../core/money.ts'
import {
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

async function shippedOrder(testApp: TestApp, customerId: number) {
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(testApp, { sellerId: seller.id, priceCents: cents(24_000) })
  const cartId = await cartWithArtwork(testApp, { customerId, listingId: listing.id })
  const order = await placeCustomerOrder(testApp, { cartId, customerId })
  await payForOrder(testApp, { orderId: order.id })

  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  return { order, fulfillment, seller }
}

/** The fulfillment's status and ledger rows, so a refused request can be
 * checked for leaving both exactly as they were. */
async function fulfillmentState(testApp: TestApp, fulfillmentId: number) {
  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillmentId)
    .executeTakeFirstOrThrow()
  const ledgerEntries = await testApp.db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('fulfillmentId', '=', fulfillmentId)
    .orderBy('id')
    .execute()

  return { fulfillment, ledgerEntries }
}

test('confirming delivery closes the fulfillment and releases the escrow', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { order, fulfillment } = await shippedOrder(testApp, customer.id)
  await markShipped(testApp, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123456789GB',
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${fulfillment.id}/delivered`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/orders/${order.id}`)

  const delivered = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(delivered.status, 'delivered')

  const rolledUp = await testApp.db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', order.id)
    .executeTakeFirstOrThrow()
  assert.equal(rolledUp.status, 'delivered')

  const released = await testApp.db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('fulfillmentId', '=', fulfillment.id)
    .where('entryType', '=', 'released')
    .execute()
  assert.equal(released.length, 1)
})

test('a fulfillment the seller has not shipped cannot be confirmed, and flashes why', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { order, fulfillment } = await shippedOrder(testApp, customer.id)
  const before = await fulfillmentState(testApp, fulfillment.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${fulfillment.id}/delivered`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/orders/${order.id}`)
  assert.deepEqual(await fulfillmentState(testApp, fulfillment.id), before)
})

test('confirming a delivery twice redirects with a flash the second time instead of erroring', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { order, fulfillment } = await shippedOrder(testApp, customer.id)
  await markShipped(testApp, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123456789GB',
  })

  await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${fulfillment.id}/delivered`,
    cookies: customer.cookies,
  })
  const afterFirst = await fulfillmentState(testApp, fulfillment.id)

  const again = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${fulfillment.id}/delivered`,
    cookies: customer.cookies,
  })

  assert.equal(again.statusCode, 302)
  assert.equal(again.headers.location, `/orders/${order.id}`)
  const afterSecond = await fulfillmentState(testApp, fulfillment.id)
  assert.deepEqual(afterSecond, afterFirst)
  assert.equal(afterSecond.fulfillment.status, 'delivered')
  assert.equal(
    afterSecond.ledgerEntries.filter((entry) => entry.entryType === 'released').length,
    1,
  )
})

test("another customer cannot confirm someone else's delivery", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const buyer = await signInAsCustomer(testApp, 'buyer@example.com')
  const stranger = await signInAsCustomer(testApp, 'stranger@example.com')
  const { order, fulfillment } = await shippedOrder(testApp, buyer.id)
  await markShipped(testApp, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123456789GB',
  })
  const before = await fulfillmentState(testApp, fulfillment.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${fulfillment.id}/delivered`,
    cookies: stranger.cookies,
  })

  assert.equal(response.statusCode, 404)
  const after = await fulfillmentState(testApp, fulfillment.id)
  assert.deepEqual(after, before)
  assert.equal(after.fulfillment.status, 'shipped')
})

test('a fulfillment that belongs to another order is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const first = await shippedOrder(testApp, customer.id)
  const second = await shippedOrder(testApp, customer.id)
  await markShipped(testApp, {
    fulfillmentId: second.fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123456789GB',
  })
  const before = await fulfillmentState(testApp, second.fulfillment.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${first.order.id}/fulfillments/${second.fulfillment.id}/delivered`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 404)
  const after = await fulfillmentState(testApp, second.fulfillment.id)
  assert.deepEqual(after, before)
  assert.equal(after.fulfillment.status, 'shipped')
})

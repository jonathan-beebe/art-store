import { test } from 'node:test'
import assert from 'node:assert/strict'
import { cents } from '../../core/money.ts'
import { buildTestApp, signInAsCustomer, signInAsSeller } from '../../test/build-test-app.ts'
import { cartWithArtwork, listArtwork, placeCustomerOrder } from './storefront-fixtures.ts'

test('an order item carries its lineTotalCents, priced through cartLineTotal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const customer = await signInAsCustomer(testApp)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(testApp, {
    sellerId: seller.id,
    priceCents: cents(4_500),
    quantity: 3,
  })
  const cartId = await cartWithArtwork(testApp, { customerId: customer.id, listingId: listing.id, quantity: 2 })
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 200)
  // 2 x $45.00 = $90.00 — the line total the item's own quantity multiplies to.
  assert.match(response.body, /\$90\.00/)
})

test('someone else\'s order still answers not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const owner = await signInAsCustomer(testApp, 'owner@example.com')
  const stranger = await signInAsCustomer(testApp, 'stranger@example.com')
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(testApp, { sellerId: seller.id })
  const cartId = await cartWithArtwork(testApp, { customerId: owner.id, listingId: listing.id })
  const order = await placeCustomerOrder(testApp, { cartId, customerId: owner.id })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: stranger.cookies,
  })

  assert.equal(response.statusCode, 404)
})

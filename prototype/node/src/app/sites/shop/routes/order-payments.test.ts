import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { LightMyRequestResponse } from 'fastify'
import type { CustomerId, OrderId } from '../../../core/ids/entity-ids.ts'
import { parsePrefixedId } from '../../../core/ids/prefixed-id.ts'
import { cents } from '../../../core/money.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  takeDebugMagicLink,
  type TestApp,
} from '../../../test/build-test-app.ts'
import { buildLoggedTestApp } from '../../../test/log-lines.ts'
import {
  APPROVED_CARD,
  DECLINED_CARD,
  blockCustomer,
  cartWithArtwork,
  listArtwork,
  placeCustomerOrder,
} from '../storefront-fixtures.ts'

const CHECKOUT_FORM = {
  shipping_name: 'Ada Lovelace',
  shipping_line1: '12 Analytical Way',
  shipping_city: 'London',
  shipping_region: 'Greater London',
  shipping_postal_code: 'EC1A 1BB',
  shipping_country: 'GB',
}

async function cartOneArtwork(testApp: TestApp, customerId: CustomerId) {
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(testApp, {
    sellerId: seller.id,
    title: 'Harbour at dusk',
    priceCents: cents(24_000),
  })
  const cartId = await cartWithArtwork(testApp, { customerId, listingId: listing.id })

  return { seller, listing, cartId }
}

async function orderStatus(testApp: TestApp, orderId: OrderId): Promise<string> {
  const order = await testApp.db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', orderId)
    .executeTakeFirstOrThrow()

  return order.status
}

/** The order a checkout redirect names. */
function orderIdFrom(location: string | undefined): OrderId {
  const parsed = parsePrefixedId('ord', location?.replace('/orders/', '') ?? '')
  if (parsed.outcome !== 'id') throw new Error(`no order id in redirect "${location}"`)

  return parsed.id
}

test('a guest checks out, verifies by the emailed link, and pays on the order page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const guest = await browseAsAnonymousCustomer(testApp)
  const { listing } = await cartOneArtwork(testApp, guest.id)

  const placed = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: guest.cookies,
    payload: { email: 'ada@example.test', ...CHECKOUT_FORM },
  })

  assert.equal(placed.statusCode, 302)
  const orderId = orderIdFrom(placed.headers.location)
  assert.equal(await orderStatus(testApp, orderId), 'pending_verification')

  // The card is asked for only after the address is verified, so the link the
  // guest is emailed is what carries them to the pay page.
  const link = takeDebugMagicLink(testApp, placed)
  const verified = await testApp.app.inject({
    method: 'GET',
    url: link.replace(/^https?:\/\/[^/]+/, ''),
    cookies: guest.cookies,
  })

  assert.equal(verified.statusCode, 302)
  assert.equal(verified.headers.location, `/orders/${orderId}/pay`)
  const signedIn = Object.fromEntries(
    verified.cookies.map((cookie) => [cookie.name, String(cookie.value)]),
  )

  const payPage = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${orderId}/pay`,
    cookies: signedIn,
  })

  assert.equal(payPage.statusCode, 200)
  assert.match(payPage.body, /name="card_number"/)
  assert.match(payPage.body, /\$240\.00/)
  assert.equal(await orderStatus(testApp, orderId), 'awaiting_payment')

  const paid = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${orderId}/pay`,
    cookies: signedIn,
    payload: { card_number: APPROVED_CARD },
  })

  assert.equal(paid.statusCode, 302)
  assert.equal(paid.headers.location, `/orders/${orderId}`)
  assert.equal(await orderStatus(testApp, orderId), 'paid')

  const sold = await testApp.db
    .selectFrom('listings')
    .selectAll()
    .where('id', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.equal(sold.status, 'sold')
  assert.equal(sold.quantity, 0)
})

test('a declined card names the reason, offers a retry, and hands the stock back', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { listing, cartId } = await cartOneArtwork(testApp, customer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })

  const declined = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: DECLINED_CARD },
  })

  assert.equal(declined.statusCode, 302)
  assert.equal(await orderStatus(testApp, order.id), 'payment_failed')

  const stock = await testApp.db
    .selectFrom('listings')
    .selectAll()
    .where('id', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.equal(stock.status, 'for_sale')
  assert.equal(stock.quantity, 1)

  const orderPage = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: customer.cookies,
  })
  assert.match(orderPage.body, /data-decline/)
  assert.match(orderPage.body, /Your card was declined\./)
  assert.match(orderPage.body, /name="card_number"/)

  const payPage = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
  })
  assert.match(payPage.body, /Your card was declined\./)
})

test('a retry with a card that works pays the order and takes the stock again', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { listing, cartId } = await cartOneArtwork(testApp, customer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })

  await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: DECLINED_CARD },
  })
  const retried = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: APPROVED_CARD },
  })

  assert.equal(retried.headers.location, `/orders/${order.id}`)
  assert.equal(await orderStatus(testApp, order.id), 'paid')

  const attempts = await testApp.db
    .selectFrom('payments')
    .selectAll()
    .where('orderId', '=', order.id)
    .orderBy('id')
    .execute()
  assert.equal(attempts.length, 2)
  assert.equal(attempts[0]?.status, 'declined')
  assert.equal(attempts[1]?.status, 'approved')

  const sold = await testApp.db
    .selectFrom('listings')
    .selectAll()
    .where('id', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.equal(sold.status, 'sold')
})

test('an order that is already paid has no pay page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { cartId } = await cartOneArtwork(testApp, customer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })
  await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: APPROVED_CARD },
  })

  const again = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
  })
  const charged = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: APPROVED_CARD },
  })

  assert.equal(again.statusCode, 302)
  assert.equal(again.headers.location, `/orders/${order.id}`)
  assert.equal(charged.statusCode, 404)
})

test('an unverified visitor is sent to sign in before the card form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const guest = await browseAsAnonymousCustomer(testApp)
  const { cartId } = await cartOneArtwork(testApp, guest.id)
  const order = await placeCustomerOrder(testApp, {
    cartId,
    customerId: guest.id,
    isEmailVerified: false,
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}/pay`,
    cookies: guest.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.match(response.headers.location ?? '', /^\/login\?redirect_to=/)
})

test("someone else's order cannot be paid", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const buyer = await signInAsCustomer(testApp, 'buyer@example.com')
  const stranger = await signInAsCustomer(testApp, 'stranger@example.com')
  const { cartId } = await cartOneArtwork(testApp, buyer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: buyer.id })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: stranger.cookies,
    payload: { card_number: APPROVED_CARD },
  })

  assert.equal(response.statusCode, 404)
  assert.equal(await orderStatus(testApp, order.id), 'awaiting_payment')
})

test("someone else's pay page is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const buyer = await signInAsCustomer(testApp, 'buyer@example.com')
  const stranger = await signInAsCustomer(testApp, 'stranger@example.com')
  const { cartId } = await cartOneArtwork(testApp, buyer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: buyer.id })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}/pay`,
    cookies: stranger.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('a bodiless payment is treated as an empty form and declines', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { cartId } = await cartOneArtwork(testApp, customer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(await orderStatus(testApp, order.id), 'payment_failed')
})

test('a blocked customer cannot pay and is told why', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const admin = await signInAsAdmin(testApp)
  const { cartId } = await cartOneArtwork(testApp, customer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })
  await blockCustomer(testApp, { customerId: customer.id, adminId: admin.id })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: APPROVED_CARD },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/orders/${order.id}`)
  assert.equal(await orderStatus(testApp, order.id), 'awaiting_payment')

  const orderPage = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${order.id}`,
    cookies: { ...customer.cookies, ...flashFrom(response) },
  })
  assert.match(orderPage.body, /Your account is on hold/)
})

test('a successful charge closes order.pay with did', async (t) => {
  const testApp = await buildLoggedTestApp({ logLevel: 'info' })
  const log = testApp.logLines
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { cartId } = await cartOneArtwork(testApp, customer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })

  await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: APPROVED_CARD },
  })

  const did = log.data('order.pay', 'did')
  assert.equal(did.order_id, order.id)
  assert.equal(did.amount_cents, 24_000)
  assert.equal(did.status, 'paid')
  // The card number reached the form; only its last four may reach the log.
  assert.equal(log.text().includes(APPROVED_CARD), false)
  assert.equal(did.card_last_four, APPROVED_CARD.slice(-4))
})

test('a declined card closes order.pay with refused rather than failed', async (t) => {
  const testApp = await buildLoggedTestApp({ logLevel: 'info' })
  const log = testApp.logLines
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const { cartId } = await cartOneArtwork(testApp, customer.id)
  const order = await placeCustomerOrder(testApp, { cartId, customerId: customer.id })

  await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: DECLINED_CARD },
  })

  const refused = log.line('order.pay', 'refused')
  assert.equal(refused.level, 'info')
  assert.equal(typeof refused.duration_ms, 'number')
  const data = refused.data as Record<string, unknown>
  assert.equal(data.order_id, order.id)
  assert.equal(data.amount_cents, 24_000)
  assert.equal(typeof data.decline_reason, 'string')
})

function flashFrom(response: LightMyRequestResponse): Record<string, string> {
  const flash = response.cookies.find((cookie) => cookie.name === 'flash')

  return flash === undefined ? {} : { flash: String(flash.value) }
}

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { addToCart } from '../../../actions/carts/add-to-cart.ts'
import { currentCart } from '../../../actions/carts/current-cart.ts'
import { changeListingStatus } from '../../../actions/listings/change-listing-status.ts'
import type {
  CustomerId,
  ListingId,
} from '../../../core/ids/entity-ids.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  takeDebugMagicLink,
  type SignedInActor,
  type TestApp,
} from '../../../test/build-test-app.ts'
import { buildLoggedTestApp } from '../../../test/log-lines.ts'
import { blockCustomer, listArtwork, removeListing } from '../storefront-fixtures.ts'
import { cents } from '../../../core/money.ts'

const APPROVED_CARD = '4242 4242 4242 4242'
const DECLINED_CARD = '4000 0000 0000 0002'

async function putInCart(
  testApp: TestApp,
  customerId: CustomerId,
  listingId: ListingId,
  quantity = 1,
): Promise<void> {
  const cart = await currentCart({ db: testApp.db, clock: testApp.clock }, customerId)
  await addToCart({ db: testApp.db, clock: testApp.clock }, { cartId: cart.id, listingId, quantity })
}

async function readyCart(
  testApp: TestApp,
  customer: SignedInActor<CustomerId>,
): Promise<ListingId> {
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(testApp, {
    sellerId: seller.id,
    title: 'Harbour at dusk',
    priceCents: cents(24_000),
  })
  await putInCart(testApp, customer.id, listing.id)

  return listing.id
}

function shippingPayload(overrides: Record<string, string> = {}): Record<string, string> {
  return {
    shipping_name: 'Ada Lovelace',
    shipping_line1: '1 Analytical Engine Way',
    shipping_city: 'London',
    shipping_region: 'London',
    shipping_postal_code: 'SW1A 1AA',
    shipping_country: 'United Kingdom',
    ...overrides,
  }
}

test('a signed-in verified customer pays and lands on the order page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  await readyCart(testApp, customer)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { email: 'buyer@example.com', ...shippingPayload(), card_number: APPROVED_CARD },
  })

  const order = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', customer.id)
    .executeTakeFirstOrThrow()

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/orders/${order.id}`)
  assert.equal(order.status, 'paid')
})

test('a signed-in customer whose card is declined still lands on the order page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  await readyCart(testApp, customer)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { email: 'buyer@example.com', ...shippingPayload(), card_number: DECLINED_CARD },
  })

  const order = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', customer.id)
    .executeTakeFirstOrThrow()

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/orders/${order.id}`)
  assert.equal(order.status, 'payment_failed')
})

test('a guest checks out, verifies later, and is asked for no card yet', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const guest = await browseAsAnonymousCustomer(testApp)
  await readyCart(testApp, guest)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: guest.cookies,
    payload: { email: 'guest@example.com', ...shippingPayload() },
  })

  const order = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', guest.id)
    .executeTakeFirstOrThrow()
  const payments = await testApp.db
    .selectFrom('payments')
    .selectAll()
    .where('orderId', '=', order.id)
    .execute()
  const magicLink = await testApp.db
    .selectFrom('magicLinks')
    .selectAll()
    .where('email', '=', 'guest@example.com')
    .executeTakeFirstOrThrow()

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/orders/${order.id}`)
  assert.equal(order.status, 'pending_verification')
  assert.equal(payments.length, 0)
  assert.equal(magicLink.redirectTo, `/orders/${order.id}/pay`)
  assert.doesNotThrow(() => takeDebugMagicLink(testApp, response))
})

test('an incomplete form is rejected, names what is missing, and places no order', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const guest = await browseAsAnonymousCustomer(testApp)
  await readyCart(testApp, guest)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: guest.cookies,
    payload: { email: 'guest@example.com', ...shippingPayload({ shipping_city: '' }) },
  })

  const orders = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', guest.id)
    .execute()

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="shipping_city"[^>]*>Enter the city\./)
  assert.equal(orders.length, 0)
})

test('an address that is not an email address is rejected', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const guest = await browseAsAnonymousCustomer(testApp)
  await readyCart(testApp, guest)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: guest.cookies,
    payload: { email: 'not-an-email', ...shippingPayload() },
  })

  const orders = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', guest.id)
    .execute()

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="email"[^>]*>Enter a valid email address\./)
  assert.equal(orders.length, 0)
})

test('a bodiless POST with a non-empty cart renders the form again instead of failing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  await readyCart(testApp, customer)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="email"[^>]*>Enter a valid email address\./)
  assert.match(response.body, /data-field-error="shipping_name"[^>]*>Enter the full name\./)
})

test('an empty cart is sent back to the cart instead of checkout', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/checkout',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/cart')
})

test('a blocked customer is refused checkout and places no order', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  await readyCart(testApp, customer)
  await blockCustomer(testApp, { customerId: customer.id, adminId: admin.id })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { email: 'buyer@example.com', ...shippingPayload(), card_number: APPROVED_CARD },
  })

  const orders = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', customer.id)
    .execute()
  const flashCookie = response.cookies.find((cookie) => cookie.name === 'flash')
  const unsigned = flashCookie === undefined ? null : testApp.app.unsignCookie(String(flashCookie.value))
  const flash: { alert?: string } = unsigned?.value === null || unsigned?.value === undefined
    ? {}
    : (JSON.parse(unsigned.value) as { alert?: string })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/cart')
  assert.match(flash.alert ?? '', /on hold/)
  assert.equal(orders.length, 0)
})

async function checkOut(
  testApp: TestApp,
  customer: SignedInActor,
  email: string,
): Promise<{ statusCode: number; body: string }> {
  const response = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { email, ...shippingPayload(), card_number: APPROVED_CARD },
  })

  return { statusCode: response.statusCode, body: response.body }
}

async function countRows(testApp: TestApp, customerId: CustomerId): Promise<{ orders: number; payments: number }> {
  const orders = await testApp.db
    .selectFrom('orders')
    .select('id')
    .where('customerId', '=', customerId)
    .execute()
  const payments = await testApp.db
    .selectFrom('payments')
    .select('payments.id')
    .innerJoin('orders', 'orders.id', 'payments.orderId')
    .where('orders.customerId', '=', customerId)
    .execute()

  return { orders: orders.length, payments: payments.length }
}

test('a piece an admin removed while the cart sat is refused by name and places no order', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  const listingId = await readyCart(testApp, customer)
  await removeListing(testApp, { listingId, adminId: admin.id })

  const response = await checkOut(testApp, customer, 'buyer@example.com')

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /role="alert"/)
  assert.match(response.body, /Harbour at dusk — no longer available/)
  assert.deepEqual(await countRows(testApp, customer.id), { orders: 0, payments: 0 })
})

test('a piece another buyer took while the cart sat is refused as sold out', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  const listingId = await readyCart(testApp, customer)
  const quicker = await signInAsCustomer(testApp, 'quicker@example.com')
  await putInCart(testApp, quicker.id, listingId)
  await checkOut(testApp, quicker, 'quicker@example.com')

  const response = await checkOut(testApp, customer, 'buyer@example.com')

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /Harbour at dusk — sold out/)
  assert.deepEqual(await countRows(testApp, customer.id), { orders: 0, payments: 0 })
})

test('a piece the seller archived while the cart sat is refused as off sale', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  const listingId = await readyCart(testApp, customer)
  await changeListingStatus(testApp, { listingId, status: 'archived' })

  const response = await checkOut(testApp, customer, 'buyer@example.com')

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /Harbour at dusk — no longer for sale/)
  assert.deepEqual(await countRows(testApp, customer.id), { orders: 0, payments: 0 })
})

test('a refused checkout leaves the cart as it was', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  const listingId = await readyCart(testApp, customer)
  await removeListing(testApp, { listingId, adminId: admin.id })

  await checkOut(testApp, customer, 'buyer@example.com')

  const cart = await currentCart({ db: testApp.db, clock: testApp.clock }, customer.id)
  const items = await testApp.db.selectFrom('cartItems').select('id').where('cartId', '=', cart.id).execute()

  assert.equal(items.length, 1)
})

test('a paid checkout leaves the order and its payment together', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  await readyCart(testApp, customer)

  await checkOut(testApp, customer, 'buyer@example.com')

  assert.deepEqual(await countRows(testApp, customer.id), { orders: 1, payments: 1 })
})

test('checking out tells the whole story in order under one request, session, and unit of work', async (t) => {
  const testApp = await buildLoggedTestApp({ logLevel: 'info' })
  const log = testApp.logLines
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  await readyCart(testApp, customer)

  await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { email: 'buyer@example.com', ...shippingPayload(), card_number: APPROVED_CARD },
  })

  const order = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', customer.id)
    .executeTakeFirstOrThrow()

  // The story a reader of the log sees, in the order it was written.
  const story = log.story()
  assert.deepEqual(story.slice(0, 3), [
    'http.request will',
    'order.place will',
    'order.place did',
  ])
  assert.equal(story.at(-1), 'http.request did')
  assert.deepEqual(story.filter((line: string) => line.startsWith('order.pay ')), [
    'order.pay will',
    'order.pay did',
  ])

  const placed = log.data('order.place', 'did')
  assert.equal(placed.order_id, order.id)
  assert.equal(placed.total_cents, 24_000)
  assert.equal(log.data('order.pay', 'did').order_id, order.id)

  // One request id, one session id, and one unit of work across the whole story.
  assert.equal(new Set(log.lines().map((line: Record<string, unknown>) => line.request_id)).size, 1)
  assert.equal(new Set(log.lines().map((line: Record<string, unknown>) => line.session_id)).size, 1)
  assert.equal(
    log.line('order.place', 'will').txn_id,
    log.line('order.place', 'did').txn_id,
  )
  assert.equal(log.line('order.pay', 'did').txn_id, log.line('order.place', 'did').txn_id)

  assert.equal(typeof log.line('order.place', 'did').duration_ms, 'number')
  assert.equal(typeof log.line('http.request', 'did').duration_ms, 'number')
  assert.equal(log.line('http.request', 'will').txn_id, undefined)
})

test('an http.request line carries every always-present field and nothing renamed', async (t) => {
  const testApp = await buildLoggedTestApp({ logLevel: 'info' })
  const log = testApp.logLines
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  await readyCart(testApp, customer)

  await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { email: 'buyer@example.com', ...shippingPayload(), card_number: APPROVED_CARD },
  })

  const will = log.line('http.request', 'will')
  assert.match(String(will.ts), /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/)
  assert.equal(will.level, 'info')
  assert.equal(will.event, 'http.request')
  assert.equal(will.phase, 'will')
  assert.equal(will.msg, '🎬 POST /checkout')
  assert.equal(typeof will.request_id, 'string')
  assert.match(String(will.session_id), /^ses_[0-9A-HJKMNP-TV-Z]{26}$/)
  assert.equal(will.actor_type, 'customer')
  assert.equal(will.actor_id, customer.id)
  assert.deepEqual(will.data, { method: 'POST', path: '/checkout' })
  assert.equal(will.time, undefined)
  assert.equal(will.reqId, undefined)

  const placed = log.line('order.place', 'did')
  assert.match(String(placed.ts), /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/)
  assert.equal(placed.level, 'info')
  assert.equal(placed.phase, 'did')
  assert.equal(placed.msg, 'placed the order')
  assert.equal(placed.request_id, will.request_id)
  assert.equal(placed.session_id, will.session_id)
  assert.equal(placed.actor_type, 'customer')
  assert.equal(placed.actor_id, customer.id)
  assert.match(String(placed.txn_id), /^txn_[0-9A-HJKMNP-TV-Z]{26}$/)
  assert.equal(typeof placed.duration_ms, 'number')
})

test('a guest checkout places an order with no charge behind it yet', async (t) => {
  const testApp = await buildLoggedTestApp({ logLevel: 'info' })
  const log = testApp.logLines
  t.after(testApp.close)
  const customer = await browseAsAnonymousCustomer(testApp)
  await readyCart(testApp, customer)

  await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { email: 'guest@example.com', ...shippingPayload(), card_number: APPROVED_CARD },
  })

  assert.equal(log.line('order.place', 'did').phase, 'did')
  assert.deepEqual(log.linesFor('order.pay'), [])
  // The guest's address rode in on the form and must not ride out in the log.
  assert.equal(log.text().includes('guest@example.com'), false)
})

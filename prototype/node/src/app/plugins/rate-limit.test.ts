import { test } from 'node:test'
import assert from 'node:assert/strict'
import { addToCart } from '../actions/carts/add-to-cart.ts'
import { currentCart } from '../actions/carts/current-cart.ts'
import { openConversation } from '../actions/messaging/open-conversation.ts'
import type { Clock } from '../clock.ts'
import type { AppConfig } from '../config.ts'
import type { CustomerId, ListingId } from '../core/ids/entity-ids.ts'
import type { RateLimitName } from '../core/rate-limit/rate-limit-name.ts'
import type { RateLimit } from '../core/rate-limit/rate-limit-value.ts'
import { seedAdmins } from '../db/seed-admins.ts'
import { cartWithArtwork, listArtwork, placeCustomerOrder } from '../sites/shop/storefront-fixtures.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsCustomer,
  signInAsSeller,
  TEST_CONFIG,
  type TestApp,
} from '../test/build-test-app.ts'
import { buildLoggedTestApp } from '../test/log-lines.ts'

const APPROVED_CARD = '4242 4242 4242 4242'

/** `TEST_CONFIG.rateLimits` has every limit `off`; this turns on the one or
 * two a test is about, leaving the rest off so nothing else in the request it
 * drives (identity resolution, page views, …) can trip unrelated to what the
 * test asserts. */
function rateLimitedConfig(
  overrides: Partial<Record<RateLimitName, RateLimit>>,
  logLevel: AppConfig['logLevel'] = TEST_CONFIG.logLevel,
): AppConfig {
  return {
    ...TEST_CONFIG,
    logLevel,
    rateLimits: { ...TEST_CONFIG.rateLimits, ...overrides },
  }
}

function shippingPayload(email: string): Record<string, string> {
  return {
    email,
    shipping_name: 'Ada Lovelace',
    shipping_line1: '1 Analytical Engine Way',
    shipping_city: 'London',
    shipping_region: 'London',
    shipping_postal_code: 'SW1A 1AA',
    shipping_country: 'United Kingdom',
  }
}

function listingFields(overrides: Record<string, string> = {}): Record<string, string> {
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

function multipartPayload(fields: Record<string, string>, boundary = '----ratelimittest'): Buffer {
  const parts = Object.entries(fields).flatMap(([name, value]) => [
    `--${boundary}`,
    `Content-Disposition: form-data; name="${name}"`,
    '',
    value,
  ])
  parts.push(`--${boundary}--`, '')

  return Buffer.from(parts.join('\r\n'))
}

function multipartHeaders(boundary = '----ratelimittest'): Record<string, string> {
  return { 'content-type': `multipart/form-data; boundary=${boundary}` }
}

async function putInCart(testApp: TestApp, customerId: CustomerId, listingId: ListingId): Promise<void> {
  const cart = await currentCart({ db: testApp.db, clock: testApp.clock }, customerId)
  await addToCart({ db: testApp.db, clock: testApp.clock }, { cartId: cart.id, listingId, quantity: 1 })
}

test('magic_link_request trips per email address, and sends no further link once tripped', async (t) => {
  const testApp = await buildLoggedTestApp({
    config: rateLimitedConfig({ magic_link_request: { count: 2, windowSeconds: 900 } }, 'debug'),
  })
  t.after(testApp.close)
  const payload = { email: 'ada@example.com' }

  const first = await testApp.app.inject({ method: 'POST', url: '/login', payload })
  const second = await testApp.app.inject({ method: 'POST', url: '/login', payload })
  const third = await testApp.app.inject({ method: 'POST', url: '/login', payload })

  assert.equal(first.statusCode, 302)
  assert.equal(second.statusCode, 302)
  assert.equal(third.statusCode, 429)
  assert.match(String(third.headers['retry-after']), /^\d+$/)
  assert.match(third.body, /Too many requests — try again in \d+ minutes\./)
  assert.match(third.body, /— Art Store<\/title>/)

  const links = await testApp.db.selectFrom('magicLinks').selectAll().execute()
  assert.equal(links.length, 2)

  const line = testApp.logLines.line('rate_limit.exceed', 'did')
  assert.equal(line.level, 'warn')
  const data = testApp.logLines.data('rate_limit.exceed', 'did')
  assert.equal(data.limit, 'magic_link_request')
  assert.equal(typeof data.retry_after_seconds, 'number')
  assert.equal(typeof data.key, 'string')
  assert.doesNotMatch(testApp.logLines.text(), /ada@example\.com/)
})

test('a tripped magic_link_request also tells magic_link.request refused, without the address or the ip', async (t) => {
  const testApp = await buildLoggedTestApp({
    config: rateLimitedConfig({ magic_link_request: { count: 1, windowSeconds: 900 } }, 'debug'),
  })
  t.after(testApp.close)
  const payload = { email: 'ada@example.com' }

  await testApp.app.inject({ method: 'POST', url: '/login', payload })
  const tripped = await testApp.app.inject({ method: 'POST', url: '/login', payload })
  assert.equal(tripped.statusCode, 429)

  const exceeded = testApp.logLines.line('rate_limit.exceed', 'did')
  assert.equal(exceeded.level, 'warn')

  const refused = testApp.logLines.line('magic_link.request', 'refused')
  assert.equal(refused.level, 'info')
  const data = testApp.logLines.data('magic_link.request', 'refused')
  assert.equal(data.reason, 'rate_limited')
  assert.equal(typeof data.key, 'string')

  assert.doesNotMatch(testApp.logLines.text(), /ada@example\.com/)
  assert.doesNotMatch(testApp.logLines.text(), /127\.0\.0\.1/)
})

test('magic_link_request also trips per client ip, shared across different email addresses', async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ magic_link_request: { count: 2, windowSeconds: 900 } }),
  })
  t.after(testApp.close)

  const first = await testApp.app.inject({ method: 'POST', url: '/login', payload: { email: 'ada@example.com' } })
  const second = await testApp.app.inject({ method: 'POST', url: '/login', payload: { email: 'grace@example.com' } })
  const third = await testApp.app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'katherine@example.com' },
  })

  assert.equal(first.statusCode, 302)
  assert.equal(second.statusCode, 302)
  // Three different addresses, none of which alone exceeded its own count —
  // the shared ip is what trips the third.
  assert.equal(third.statusCode, 429)
})

test('magic_link_request resets once its window passes', async (t) => {
  let now = new Date('2026-08-24T12:00:00.000Z')
  const clock: Clock = { now: () => new Date(now) }
  const testApp = await buildTestApp({
    clock,
    config: rateLimitedConfig({ magic_link_request: { count: 1, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  const payload = { email: 'ada@example.com' }

  await testApp.app.inject({ method: 'POST', url: '/login', payload })
  const tripped = await testApp.app.inject({ method: 'POST', url: '/login', payload })
  assert.equal(tripped.statusCode, 429)

  now = new Date(now.getTime() + 900_000)

  const afterReset = await testApp.app.inject({ method: 'POST', url: '/login', payload })
  assert.equal(afterReset.statusCode, 302)
})

test('magic_link_consume trips per client ip', async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ magic_link_consume: { count: 2, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  const hit = () => testApp.app.inject({ method: 'GET', url: '/auth/magic/not-a-real-token' })

  const first = await hit()
  const second = await hit()
  const third = await hit()

  assert.equal(first.statusCode, 302)
  assert.equal(second.statusCode, 302)
  assert.equal(third.statusCode, 429)
  assert.match(String(third.headers['retry-after']), /^\d+$/)
})

test('checkout trips per customer and places no further order once tripped', async (t) => {
  const testApp = await buildTestApp({ config: rateLimitedConfig({ checkout: { count: 1, windowSeconds: 900 } }) })
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(
    { db: testApp.db, clock: testApp.clock },
    { sellerId: seller.id, quantity: 5 },
  )

  await putInCart(testApp, customer.id, listing.id)
  const first = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { ...shippingPayload('buyer@example.com'), card_number: APPROVED_CARD },
  })
  assert.equal(first.statusCode, 302)

  await putInCart(testApp, customer.id, listing.id)
  const second = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: customer.cookies,
    payload: { ...shippingPayload('buyer@example.com'), card_number: APPROVED_CARD },
  })
  assert.equal(second.statusCode, 429)

  const orders = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', customer.id)
    .execute()
  assert.equal(orders.length, 1)
})

test("checkout's implicit magic link respects magic_link_request and sends no second link once tripped", async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ checkout: 'off', magic_link_request: { count: 1, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  const guest = await browseAsAnonymousCustomer(testApp)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(
    { db: testApp.db, clock: testApp.clock },
    { sellerId: seller.id, quantity: 5 },
  )

  const checkoutOnce = async () => {
    await putInCart(testApp, guest.id, listing.id)

    return testApp.app.inject({
      method: 'POST',
      url: '/checkout',
      cookies: guest.cookies,
      payload: shippingPayload('guest@example.com'),
    })
  }

  const first = await checkoutOnce()
  assert.equal(first.statusCode, 302)

  const linksAfterFirst = await testApp.db.selectFrom('magicLinks').selectAll().execute()
  assert.equal(linksAfterFirst.length, 1)

  const second = await checkoutOnce()
  assert.equal(second.statusCode, 429)

  const linksAfterSecond = await testApp.db.selectFrom('magicLinks').selectAll().execute()
  assert.equal(linksAfterSecond.length, 1)

  // The checkout limit is off in this test, so both orders were placed —
  // tripping magic_link_request only blocks the link this second one asked for.
  const orders = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('customerId', '=', guest.id)
    .execute()
  assert.equal(orders.length, 2)
})

test('payment_attempt trips per order and attempts no further charge once tripped', async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ payment_attempt: { count: 1, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const context = { db: testApp.db, clock: testApp.clock }
  const listing = await listArtwork(context, { sellerId: seller.id })
  const cartId = await cartWithArtwork(context, { customerId: customer.id, listingId: listing.id })
  const order = await placeCustomerOrder(context, {
    cartId,
    customerId: customer.id,
    email: 'buyer@example.com',
    isEmailVerified: true,
  })

  const first = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: APPROVED_CARD },
  })
  assert.equal(first.statusCode, 302)

  const before = await testApp.db.selectFrom('payments').selectAll().where('orderId', '=', order.id).execute()

  const second = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/pay`,
    cookies: customer.cookies,
    payload: { card_number: APPROVED_CARD },
  })
  assert.equal(second.statusCode, 429)

  const after = await testApp.db.selectFrom('payments').selectAll().where('orderId', '=', order.id).execute()
  assert.equal(after.length, before.length)
})

test('message_post trips per actor and appends no further message once tripped', async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ message_post: { count: 1, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const context = { db: testApp.db, clock: testApp.clock }
  const listing = await listArtwork(context, { sellerId: seller.id })
  const conversation = await openConversation(context, {
    kind: 'listing_question',
    sellerId: seller.id,
    customerId: customer.id,
    listingId: listing.id,
  })

  const first = await testApp.app.inject({
    method: 'POST',
    url: `/messages/${conversation.id}`,
    cookies: customer.cookies,
    payload: { body: 'Is this framed?' },
  })
  assert.equal(first.statusCode, 302)

  const before = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .execute()

  const second = await testApp.app.inject({
    method: 'POST',
    url: `/messages/${conversation.id}`,
    cookies: customer.cookies,
    payload: { body: 'Also, does it ship internationally?' },
  })
  assert.equal(second.statusCode, 429)

  const after = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .execute()
  assert.equal(after.length, before.length)
})

test('conversation_open trips per actor', async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ conversation_open: { count: 1, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  await seedAdmins(testApp)
  const customer = await signInAsCustomer(testApp, 'buyer@example.com')

  const first = await testApp.app.inject({ method: 'GET', url: '/support', cookies: customer.cookies })
  assert.equal(first.statusCode, 302)

  const second = await testApp.app.inject({ method: 'GET', url: '/support', cookies: customer.cookies })
  assert.equal(second.statusCode, 429)
})

test('listing_write trips per seller and creates no further listing once tripped', async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ listing_write: { count: 1, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')

  const first = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(listingFields()),
  })
  assert.equal(first.statusCode, 302)

  const before = await testApp.db.selectFrom('listings').selectAll().where('sellerId', '=', seller.id).execute()

  const second = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(listingFields({ title: 'A Second Piece' })),
  })
  assert.equal(second.statusCode, 429)

  const after = await testApp.db.selectFrom('listings').selectAll().where('sellerId', '=', seller.id).execute()
  assert.equal(after.length, before.length)
})

test('listing_write also trips the update route, keyed by the same seller', async (t) => {
  const testApp = await buildTestApp({
    config: rateLimitedConfig({ listing_write: { count: 1, windowSeconds: 900 } }),
  })
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork({ db: testApp.db, clock: testApp.clock }, { sellerId: seller.id })

  const first = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}`,
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(listingFields()),
  })
  assert.equal(first.statusCode, 302)

  const second = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload(listingFields({ title: 'A Second Piece' })),
  })
  assert.equal(second.statusCode, 429)
})

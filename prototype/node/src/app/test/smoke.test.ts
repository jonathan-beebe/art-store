import { mkdtemp, readFile, rm } from 'node:fs/promises'
import type { OutgoingHttpHeaders } from 'node:http'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { LightMyRequestResponse } from 'fastify'
import { drainOutbox } from '../actions/outbox/drain-outbox.ts'
import { fixedClock } from '../clock.ts'
import type {
  ActorId,
  AdminId,
  FulfillmentId,
  OrderId,
  SellerId,
} from '../core/ids/entity-ids.ts'
import { cents, formatCents, subtractCents } from '../core/money.ts'
import type { Listing } from '../db/commerce-schema.ts'
import { outboxMagicLinkDelivery } from '../delivery/outbox-magic-link-delivery.ts'
import { APPROVED_CARD, cartWithArtwork, DECLINED_CARD, listArtwork, TEST_SHIPPING } from '../sites/shop/storefront-fixtures.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  takeDebugMagicLink,
  TEST_CONFIG,
} from './build-test-app.ts'

/** Where a redirect sent the visitor. */
function destinationOf(response: { headers: OutgoingHttpHeaders }): string {
  return String(response.headers.location ?? '')
}

/** The cookies a response set, keyed by name — what the next request in the
 * walk presents back. */
function cookiesFrom(response: LightMyRequestResponse): Record<string, string> {
  return Object.fromEntries(response.cookies.map((cookie) => [cookie.name, String(cookie.value)]))
}

/** A magic link is an absolute URL — a mail client has no idea where the app
 * is served — so following one in a test means injecting its path alone. */
function pathOf(url: string): string {
  return new URL(url).pathname
}

function escapedForPattern(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

test('every site serves its own page and they all share one stylesheet', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  // The seller portal and the admin site are both behind their guard, so
  // reaching either page means signing in.
  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)

  const storefront = await app.inject({ method: 'GET', url: '/' })
  const portal = await app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })
  const admin = await app.inject({ method: 'GET', url: '/admin', cookies: operator.cookies })

  assert.equal(storefront.statusCode, 200)
  assert.equal(portal.statusCode, 200)
  assert.equal(admin.statusCode, 200)
  assert.match(storefront.body, /Art Store<\/title>/)
  assert.match(portal.body, /Seller portal<\/title>/)
  assert.match(admin.body, /Admin<\/title>/)

  // The stylesheet every layout links is built by the entrypoint, so a serving
  // container answers for it.
  const stylesheet = await app.inject({ method: 'GET', url: '/app.css' })

  assert.equal(stylesheet.statusCode, 200)
  assert.match(stylesheet.headers['content-type'] ?? '', /text\/css/)

  // What docker-compose's healthcheck polls before routing traffic here.
  const health = await app.inject({ method: 'GET', url: '/health' })
  const healthBody = health.json<{ status: string; checks: unknown; uptimeSeconds: number }>()

  assert.equal(health.statusCode, 200)
  assert.deepEqual(healthBody, {
    status: 'ok',
    checks: { database: 'ok', migrations: 'current' },
    uptimeSeconds: healthBody.uptimeSeconds,
  })
  assert.equal(typeof healthBody.uptimeSeconds, 'number')
})

// ---------------------------------------------------------------------------
// A listing travels from the seller signing in to their weekly payout.
// ---------------------------------------------------------------------------

const PIECE_TITLE = 'Meadow at Low Tide'
const PIECE_PRICE_CENTS = cents(48_000) // $480.00
const PLATFORM_FEE_CENTS = cents(4_800) // 10% of the subtotal
const SELLER_NET_CENTS = subtractCents(PIECE_PRICE_CENTS, PLATFORM_FEE_CENTS) // $432.00
const CARRIER = 'Royal Mail'
const TRACKING_NUMBER = 'RM123456789GB'
const SELLER_EMAIL = 'smoke-seller@example.test'
const CUSTOMER_EMAIL = 'smoke-buyer@example.test'

// A Wednesday, so the week the delivery lands in is the week the payout run
// settles whatever day the suite runs on.
const FROZEN_NOW = new Date('2026-08-19T09:00:00.000Z')
const PAYOUT_AS_OF = '2026-08-24'

type Actor<Id extends ActorId = ActorId> = { id: Id; cookies: Record<string, string> }
type Visitor = { cookies: Record<string, string> }

function multipartHeaders(boundary = '----smoketest'): Record<string, string> {
  return { 'content-type': `multipart/form-data; boundary=${boundary}` }
}

function multipartPayload(fields: Record<string, string>, boundary = '----smoketest'): Buffer {
  const parts = Object.entries(fields).flatMap(([name, value]) => [
    `--${boundary}`,
    `Content-Disposition: form-data; name="${name}"`,
    '',
    value,
  ])
  parts.push(`--${boundary}--`, '')

  return Buffer.from(parts.join('\r\n'))
}

async function signSellerIn(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
): Promise<Actor<SellerId>> {
  const asked = await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: SELLER_EMAIL },
  })
  const link = takeDebugMagicLink(testApp, asked)
  const followed = await testApp.app.inject({ method: 'GET', url: pathOf(link) })

  assert.equal(followed.headers.location, '/seller')

  const seller = await testApp.db
    .selectFrom('sellers')
    .selectAll()
    .where('email', '=', SELLER_EMAIL)
    .executeTakeFirstOrThrow()

  assert.notEqual(seller.emailVerifiedAt, null)

  return { id: seller.id, cookies: cookiesFrom(followed) }
}

async function listThePiece(testApp: Awaited<ReturnType<typeof buildTestApp>>, seller: Actor<SellerId>): Promise<Listing> {
  const created = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings',
    cookies: seller.cookies,
    headers: multipartHeaders(),
    payload: multipartPayload({
      title: PIECE_TITLE,
      description: 'Oil on linen, painted at the tideline.',
      medium: 'Oil on linen',
      dimensions: '40 x 60 cm',
      price: '480.00',
      quantity: '1',
    }),
  })

  assert.equal(created.statusCode, 302)

  const listing = await testApp.db
    .selectFrom('listings')
    .selectAll()
    .where('sellerId', '=', seller.id)
    .executeTakeFirstOrThrow()

  assert.equal(listing.status, 'draft')
  assert.equal(listing.priceCents, PIECE_PRICE_CENTS)

  return listing
}

async function putUpForSale(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  seller: Actor<SellerId>,
  listing: Listing,
): Promise<Listing> {
  const changed = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/status`,
    cookies: seller.cookies,
    payload: { status: 'for_sale' },
  })

  assert.equal(changed.statusCode, 302)

  const index = await testApp.app.inject({ method: 'GET', url: '/seller/listings', cookies: seller.cookies })
  assert.match(index.body, new RegExp(`data-listing="${listing.id}"[\\s\\S]*?data-cell="status"[^>]*>For sale<`))

  const updated = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(updated.status, 'for_sale')

  return updated
}

async function arriveAtTheStorefront(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  listing: Listing,
): Promise<Visitor> {
  const home = await testApp.app.inject({ method: 'GET', url: '/' })

  assert.equal(home.statusCode, 200)
  assert.match(home.body, new RegExp(`/art/${listing.slug}"[\\s\\S]*?${listing.title}`))

  const visitor = await testApp.db.selectFrom('customers').selectAll().executeTakeFirstOrThrow()
  assert.equal(visitor.email, null)

  return { cookies: cookiesFrom(home) }
}

async function viewTheListing(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
  listing: Listing,
): Promise<void> {
  const page = await testApp.app.inject({ method: 'GET', url: `/art/${listing.slug}`, cookies: shopper.cookies })

  assert.equal(page.statusCode, 200)
  assert.match(page.body, new RegExp(`<h1[^>]*>${listing.title}`))
  assert.match(page.body, new RegExp(escapedForPattern(formatCents(listing.priceCents))))

  const views = await testApp.db
    .selectFrom('listingEvents')
    .selectAll()
    .where('listingId', '=', listing.id)
    .where('eventType', '=', 'view')
    .execute()
  assert.equal(views.length, 1)
}

async function favoriteTheListing(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
  listing: Listing,
): Promise<void> {
  const toggled = await testApp.app.inject({
    method: 'POST',
    url: `/art/${listing.slug}/favorite`,
    cookies: shopper.cookies,
  })
  assert.equal(toggled.statusCode, 302)

  const favorites = await testApp.app.inject({ method: 'GET', url: '/favorites', cookies: shopper.cookies })
  assert.equal(favorites.statusCode, 200)
  assert.match(favorites.body, new RegExp(`/art/${listing.slug}`))

  const rows = await testApp.db.selectFrom('favorites').selectAll().where('listingId', '=', listing.id).execute()
  assert.equal(rows.length, 1)
}

async function cartThePiece(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
  listing: Listing,
): Promise<void> {
  const added = await testApp.app.inject({ method: 'POST', url: `/cart/${listing.slug}`, cookies: shopper.cookies })
  assert.equal(added.statusCode, 302)
  assert.equal(destinationOf(added), '/cart')

  const cart = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: shopper.cookies })
  assert.equal(cart.statusCode, 200)
  assert.match(
    cart.body,
    new RegExp(`data-cart-subtotal[^>]*>${escapedForPattern(formatCents(PIECE_PRICE_CENTS))}`),
  )
}

type GuestCheckout = { orderId: OrderId; verificationLink: string }

async function guestChecksOut(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
): Promise<GuestCheckout> {
  const form = await testApp.app.inject({ method: 'GET', url: '/checkout', cookies: shopper.cookies })
  assert.equal(form.statusCode, 200)
  assert.doesNotMatch(form.body, /name="card_number"/)

  const placed = await testApp.app.inject({
    method: 'POST',
    url: '/checkout',
    cookies: shopper.cookies,
    payload: {
      email: CUSTOMER_EMAIL,
      shipping_name: TEST_SHIPPING.name,
      shipping_line1: TEST_SHIPPING.line1,
      shipping_city: TEST_SHIPPING.city,
      shipping_region: TEST_SHIPPING.region,
      shipping_postal_code: TEST_SHIPPING.postalCode,
      shipping_country: TEST_SHIPPING.country,
    },
  })

  const order = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('email', '=', CUSTOMER_EMAIL)
    .executeTakeFirstOrThrow()

  assert.equal(placed.statusCode, 302)
  assert.equal(destinationOf(placed), `/orders/${order.id}`)
  assert.equal(order.status, 'pending_verification')

  const payments = await testApp.db.selectFrom('payments').selectAll().where('orderId', '=', order.id).execute()
  assert.equal(payments.length, 0)

  return { orderId: order.id, verificationLink: takeDebugMagicLink(testApp, placed) }
}

async function verifyTheAddress(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
  checkout: GuestCheckout,
): Promise<void> {
  const followed = await testApp.app.inject({
    method: 'GET',
    url: pathOf(checkout.verificationLink),
    cookies: shopper.cookies,
  })
  assert.equal(destinationOf(followed), `/orders/${checkout.orderId}/pay`)

  const payPage = await testApp.app.inject({
    method: 'GET',
    url: destinationOf(followed),
    cookies: shopper.cookies,
  })
  assert.equal(payPage.statusCode, 200)
  assert.match(payPage.body, /name="card_number"/)

  const order = await testApp.db.selectFrom('orders').selectAll().where('id', '=', checkout.orderId).executeTakeFirstOrThrow()
  assert.equal(order.status, 'awaiting_payment')
}

async function tryADeclinedCard(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
  checkout: GuestCheckout,
  listing: Listing,
): Promise<void> {
  const attempted = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${checkout.orderId}/pay`,
    cookies: shopper.cookies,
    payload: { card_number: DECLINED_CARD },
  })
  assert.equal(destinationOf(attempted), `/orders/${checkout.orderId}`)

  const orderPage = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${checkout.orderId}`,
    cookies: shopper.cookies,
  })
  assert.match(orderPage.body, /Your card was declined\./)

  const order = await testApp.db.selectFrom('orders').selectAll().where('id', '=', checkout.orderId).executeTakeFirstOrThrow()
  assert.equal(order.status, 'payment_failed')

  const restocked = await testApp.db.selectFrom('listings').selectAll().where('id', '=', listing.id).executeTakeFirstOrThrow()
  assert.equal(restocked.status, 'for_sale')
  assert.equal(restocked.quantity, 1)
}

async function payWithTheApprovedCard(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
  checkout: GuestCheckout,
): Promise<void> {
  const paid = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${checkout.orderId}/pay`,
    cookies: shopper.cookies,
    payload: { card_number: APPROVED_CARD },
  })
  assert.equal(destinationOf(paid), `/orders/${checkout.orderId}`)

  const orderPage = await testApp.app.inject({
    method: 'GET',
    url: `/orders/${checkout.orderId}`,
    cookies: shopper.cookies,
  })
  assert.match(orderPage.body, /data-order-status[^>]*>Paid</)

  const order = await testApp.db.selectFrom('orders').selectAll().where('id', '=', checkout.orderId).executeTakeFirstOrThrow()
  assert.equal(order.status, 'paid')

  const payment = await testApp.db
    .selectFrom('payments')
    .selectAll()
    .where('orderId', '=', checkout.orderId)
    .orderBy('id', 'desc')
    .executeTakeFirstOrThrow()
  assert.equal(payment.cardLastFour, '4242')

  const held = await testApp.db.selectFrom('ledgerEntries').selectAll().where('entryType', '=', 'held').executeTakeFirstOrThrow()
  assert.equal(held.amountCents, SELLER_NET_CENTS)
}

async function sellerReadsTheSale(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  seller: Actor<SellerId>,
  checkout: GuestCheckout,
): Promise<{ id: FulfillmentId }> {
  const notifications = await testApp.app.inject({ method: 'GET', url: '/seller/notifications', cookies: seller.cookies })
  assert.match(notifications.body, /Item sold/)
  assert.match(notifications.body, new RegExp(`Order ${checkout.orderId} is paid`))

  const orders = await testApp.app.inject({ method: 'GET', url: '/seller/orders', cookies: seller.cookies })
  assert.match(orders.body, new RegExp(`data-group="awaiting_shipment"[\\s\\S]*?${PIECE_TITLE}`))

  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('sellerId', '=', seller.id)
    .executeTakeFirstOrThrow()
  assert.equal(fulfillment.status, 'awaiting_shipment')

  return { id: fulfillment.id }
}

async function sellerShipsIt(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  seller: Actor<SellerId>,
  fulfillment: { id: FulfillmentId },
): Promise<void> {
  const shipped = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: CARRIER, tracking_number: TRACKING_NUMBER },
  })
  assert.equal(shipped.statusCode, 302)

  const page = await testApp.app.inject({ method: 'GET', url: `/seller/orders/${fulfillment.id}`, cookies: seller.cookies })
  assert.match(page.body, new RegExp(TRACKING_NUMBER))

  const updated = await testApp.db.selectFrom('fulfillments').selectAll().where('id', '=', fulfillment.id).executeTakeFirstOrThrow()
  assert.equal(updated.status, 'shipped')
}

async function customerConfirmsDelivery(
  testApp: Awaited<ReturnType<typeof buildTestApp>>,
  shopper: Visitor,
  checkout: GuestCheckout,
  fulfillment: { id: FulfillmentId },
): Promise<void> {
  const confirmed = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${checkout.orderId}/fulfillments/${fulfillment.id}/delivered`,
    cookies: shopper.cookies,
  })
  assert.equal(confirmed.statusCode, 302)

  const updatedFulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(updatedFulfillment.status, 'delivered')

  const order = await testApp.db.selectFrom('orders').selectAll().where('id', '=', checkout.orderId).executeTakeFirstOrThrow()
  assert.equal(order.status, 'delivered')

  const released = await testApp.db.selectFrom('ledgerEntries').selectAll().where('entryType', '=', 'released').executeTakeFirstOrThrow()
  assert.equal(released.amountCents, SELLER_NET_CENTS)
}

async function adminRunsTheWeeklyPayout(testApp: Awaited<ReturnType<typeof buildTestApp>>, admin: Actor<AdminId>): Promise<void> {
  const run = await testApp.app.inject({
    method: 'POST',
    url: '/admin/payouts',
    cookies: admin.cookies,
    payload: { as_of: PAYOUT_AS_OF },
  })
  assert.equal(run.statusCode, 302)

  const payouts = await testApp.db.selectFrom('payouts').selectAll().execute()
  assert.equal(payouts.length, 1)
  assert.equal(payouts[0]?.amountCents, SELLER_NET_CENTS)
}

async function sellerSeesTheNetPaidOut(testApp: Awaited<ReturnType<typeof buildTestApp>>, seller: Actor<SellerId>): Promise<void> {
  const earnings = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })
  assert.equal(earnings.statusCode, 200)

  const netLabel = escapedForPattern(formatCents(SELLER_NET_CENTS))
  assert.match(earnings.body, new RegExp(`data-stat="paid_out"[^>]*>\\s*${netLabel}\\s*<`))
  assert.match(earnings.body, new RegExp(`data-payout[\\s\\S]*?data-cell="amount"[^>]*>\\s*${netLabel}\\s*<`))
}

test('a listing travels from the seller signing in to their weekly payout', async (t) => {
  const testApp = await buildTestApp({ clock: fixedClock(FROZEN_NOW) })
  t.after(testApp.close)

  const seller = await signSellerIn(testApp)
  const draft = await listThePiece(testApp, seller)
  const listing = await putUpForSale(testApp, seller, draft)

  // Two browsers share the walk: the seller keeps a portal session while the
  // shopper arrives with no cookie at all, which is what proves the storefront
  // hands a fresh visitor an anonymous row.
  const shopper = await arriveAtTheStorefront(testApp, listing)
  await viewTheListing(testApp, shopper, listing)
  await favoriteTheListing(testApp, shopper, listing)
  await cartThePiece(testApp, shopper, listing)

  const checkout = await guestChecksOut(testApp, shopper)
  await verifyTheAddress(testApp, shopper, checkout)
  await tryADeclinedCard(testApp, shopper, checkout, listing)
  await payWithTheApprovedCard(testApp, shopper, checkout)

  const fulfillment = await sellerReadsTheSale(testApp, seller, checkout)
  await sellerShipsIt(testApp, seller, fulfillment)
  await customerConfirmsDelivery(testApp, shopper, checkout, fulfillment)

  const admin = await signInAsAdmin(testApp)
  await adminRunsTheWeeklyPayout(testApp, admin)
  await sellerSeesTheNetPaidOut(testApp, seller)
})

test('a question on a listing becomes an answer and then a published FAQ', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  const seller = await signInAsSeller(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Nine Herons' })
  const shopper = await browseAsAnonymousCustomer(testApp)

  // Anyone browsing can ask; no address has been given yet.
  const asked = await app.inject({
    method: 'POST',
    url: `/art/${listing.slug}/questions`,
    cookies: shopper.cookies,
    payload: { body: 'Is this framed?' },
  })

  assert.equal(asked.statusCode, 302)
  const threadPath = destinationOf(asked)
  assert.match(threadPath, /^\/messages\/cnv_[0-9A-HJKMNP-TV-Z]{26}$/)

  const sellerInbox = await app.inject({ url: '/seller/messages', cookies: seller.cookies })
  assert.equal(sellerInbox.statusCode, 200)
  assert.match(sellerInbox.body, /Is this framed\?/)

  const conversationId = threadPath.split('/').at(-1)
  const sellerThread = await app.inject({
    url: `/seller/messages/${conversationId}`,
    cookies: seller.cookies,
  })
  assert.equal(sellerThread.statusCode, 200)

  const answered = await app.inject({
    method: 'POST',
    url: `/seller/messages/${conversationId}`,
    cookies: seller.cookies,
    payload: { body: 'It ships unframed, ready to hang.' },
  })
  assert.equal(answered.statusCode, 302)

  const published = await app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: {
      question: 'Is this framed?',
      answer: 'It ships unframed, ready to hang.',
      redirect_to: `/seller/messages/${conversationId}`,
    },
  })
  assert.equal(published.statusCode, 302)

  // The answer one shopper got is now on the page for the next one, who never
  // asked anything at all.
  const listingPage = await app.inject({ url: `/art/${listing.slug}` })

  assert.equal(listingPage.statusCode, 200)
  assert.match(listingPage.body, /Is this framed\?/)
  assert.match(listingPage.body, /It ships unframed, ready to hang\./)

  // The asker keeps the thread even though they never gave an address.
  const shopperThread = await app.inject({ url: threadPath, cookies: shopper.cookies })

  assert.equal(shopperThread.statusCode, 200)
  assert.match(shopperThread.body, /It ships unframed, ready to hang\./)
})

test('admin removes a listing and it leaves the storefront', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  const seller = await signInAsSeller(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Coastal Light' })
  const operator = await signInAsAdmin(testApp)

  const home = await app.inject({ method: 'GET', url: '/' })
  assert.match(home.body, new RegExp(`/art/${listing.slug}`))
  const shown = await app.inject({ method: 'GET', url: `/art/${listing.slug}` })
  assert.equal(shown.statusCode, 200)

  const removed = await app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: operator.cookies,
    payload: { kind: 'temporary', reason: 'Reported as a reproduction.' },
  })
  assert.equal(removed.statusCode, 302)

  const removal = await testApp.db
    .selectFrom('listingRemovals')
    .selectAll()
    .where('listingId', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.equal(removal.liftedAt, null)

  const hiddenPage = await app.inject({ method: 'GET', url: `/art/${listing.slug}` })
  assert.equal(hiddenPage.statusCode, 404)
  const hiddenHome = await app.inject({ method: 'GET', url: '/' })
  assert.doesNotMatch(hiddenHome.body, new RegExp(`/art/${listing.slug}`))

  const lifted = await app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals/lift`,
    cookies: operator.cookies,
  })
  assert.equal(lifted.statusCode, 302)

  const restoredPage = await app.inject({ method: 'GET', url: `/art/${listing.slug}` })
  assert.equal(restoredPage.statusCode, 200)
})

test('admin blocks the customer and checkout refuses', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  const seller = await signInAsSeller(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id })
  const customer = await signInAsCustomer(testApp)
  await cartWithArtwork({ db: testApp.db, clock: testApp.clock }, { customerId: customer.id, listingId: listing.id })
  const operator = await signInAsAdmin(testApp)

  const blocked = await app.inject({
    method: 'POST',
    url: `/admin/customers/${customer.id}/blocks`,
    cookies: operator.cookies,
    payload: { reason: 'Chargeback dispute opened.' },
  })
  assert.equal(blocked.statusCode, 302)

  const refused = await app.inject({ method: 'GET', url: '/checkout', cookies: customer.cookies })
  assert.equal(destinationOf(refused), '/cart')

  const cart = await app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.match(cart.body, /data-blocked/)
  assert.match(cart.body, /Chargeback dispute opened\./)

  const lifted = await app.inject({
    method: 'POST',
    url: `/admin/customers/${customer.id}/blocks/lift`,
    cookies: operator.cookies,
  })
  assert.equal(lifted.statusCode, 302)

  const allowed = await app.inject({ method: 'GET', url: '/checkout', cookies: customer.cookies })
  assert.equal(allowed.statusCode, 200)
})

test('an admin messages a seller and the seller reads it', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)

  const opened = await app.inject({
    method: 'POST',
    url: `/admin/sellers/${seller.id}/messages`,
    cookies: operator.cookies,
  })
  assert.equal(opened.statusCode, 302)

  const adminThreadPath = destinationOf(opened)
  const sent = await app.inject({
    method: 'POST',
    url: adminThreadPath,
    cookies: operator.cookies,
    payload: { body: 'Your payout for last week is on its way.' },
  })
  assert.equal(sent.statusCode, 302)

  // The portal's nav says how much is waiting before the seller opens anything.
  const dashboard = await app.inject({ url: '/seller', cookies: seller.cookies })
  assert.match(dashboard.body, /data-unread-messages="1"/)

  const conversationId = adminThreadPath.split('/').at(-1)
  const sellerThread = await app.inject({
    url: `/seller/messages/${conversationId}`,
    cookies: seller.cookies,
  })

  assert.equal(sellerThread.statusCode, 200)
  assert.match(sellerThread.body, /Your payout for last week is on its way\./)

  const afterReading = await app.inject({ url: '/seller', cookies: seller.cookies })

  assert.doesNotMatch(afterReading.body, /data-unread-messages/)
})

test('a sign-in link asked for under outbox delivery leaves the request and drains to a file', async (t) => {
  const outboxDir = path.join(await mkdtemp(path.join(tmpdir(), 'art-store-smoke-')), 'outbox')
  t.after(() => rm(path.dirname(outboxDir), { recursive: true, force: true }))

  // The deployment a reviewer runs with MAGIC_LINK_DELIVERY=outbox: no link is
  // printed into the page that asked for it.
  const testApp = await buildTestApp({
    config: { ...TEST_CONFIG, outboxDir, showsDebugMagicLinks: false },
    magicLinkDelivery: outboxMagicLinkDelivery,
  })
  t.after(testApp.close)

  const asked = await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: SELLER_EMAIL },
  })
  assert.equal(asked.statusCode, 302)

  const landing = await testApp.app.inject({
    method: 'GET',
    url: destinationOf(asked),
    cookies: cookiesFrom(asked),
  })
  assert.doesNotMatch(landing.body, /\/auth\/magic\//)

  const queued = await testApp.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()

  assert.equal(queued.recipient, SELLER_EMAIL)
  assert.equal(queued.deliveredAt, null)
  assert.match(String(queued.url), /\/auth\/magic\/[0-9a-f]{64}$/)

  // The mailbox an admin reads on /admin/outbox is the same table.
  const operator = await signInAsAdmin(testApp)
  const mailbox = await testApp.app.inject({ url: '/admin/outbox', cookies: operator.cookies })

  assert.equal(mailbox.statusCode, 200)
  assert.match(mailbox.body, new RegExp(SELLER_EMAIL))

  const drained = await drainOutbox(
    { db: testApp.db, clock: testApp.clock },
    { outboxDir },
  )
  assert.deepEqual(
    drained.map(({ id, recipient }) => ({ id, recipient })),
    [{ id: queued.id, recipient: SELLER_EMAIL }],
  )

  const written = await readFile(path.join(outboxDir, `${queued.id}.eml`), 'utf8')

  assert.match(written, new RegExp(`^To: ${SELLER_EMAIL}$`, 'm'))
  assert.match(written, new RegExp(escapedForPattern(String(queued.url))))

  const stamped = await testApp.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.notEqual(stamped.deliveredAt, null)
})

test('the unread badge subscribes to a live event stream', async (t) => {
  // `app.inject` buffers the whole body and this response never ends, so the
  // walk needs a real socket.
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const baseUrl = await testApp.app.listen({ host: '127.0.0.1', port: 0 })
  const seller = await signInAsSeller(testApp)

  const subscribed = new AbortController()
  t.after(() => subscribed.abort())
  const stream = await fetch(`${baseUrl}/seller/events`, {
    headers: { cookie: `seller_id=${seller.cookies.seller_id ?? ''}` },
    signal: subscribed.signal,
  })

  assert.equal(stream.status, 200)
  assert.match(stream.headers.get('content-type') ?? '', /^text\/event-stream/)
  assert.ok(stream.body !== null)

  const reader: ReadableStreamDefaultReader<Uint8Array> = stream.body.getReader()
  const decoder = new TextDecoder()
  let received = ''
  while (!received.includes('event: unread\ndata: 0\n\n')) {
    const chunk = await reader.read()
    if (chunk.done) break
    received += decoder.decode(chunk.value, { stream: true })
  }

  assert.equal(received, 'retry: 3000\n\nevent: unread\ndata: 0\n\n')
})

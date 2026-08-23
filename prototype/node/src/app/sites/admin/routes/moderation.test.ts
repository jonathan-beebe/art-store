import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin, signInAsCustomer, TEST_CONFIG, type TestApp } from '../../../test/build-test-app.ts'
import { activeListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import { currentCustomerStanding } from '../../../actions/moderation/current-customer-standing.ts'
import { isOnStorefront } from '../../../core/listings/listing-availability.ts'
import { canShop } from '../../../core/moderation/customer-standing.ts'
import { cartHolding, createListing, createCustomer, createSeller } from '../../../test/commerce-world.ts'
import { captureLogLines } from '../../../test/log-lines.ts'

function flashFrom(testApp: TestApp, response: { cookies: { name: string; value: string }[] }): Record<string, string> {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')
  if (cookie === undefined) return {}

  const unsigned = testApp.app.unsignCookie(cookie.value)
  return JSON.parse(unsigned.value ?? '{}')
}

test('POST /admin/customers/:id/blocks blocks the customer and stops them shopping', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/admin/customers/${customerId}`)

  const standing = await currentCustomerStanding({ db: testApp.db }, customerId)
  assert.equal(standing.isBlocked, true)
  assert.equal(standing.reason, 'Chargeback fraud.')
  assert.equal(canShop(standing), false)
})

test('POST /admin/customers/:id/blocks honors a redirect_to on the same origin', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.', redirect_to: '/admin/customers' },
  })

  assert.equal(response.headers.location, '/admin/customers')
})

test('POST /admin/customers/:id/blocks drops a redirect_to that points off-site', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.', redirect_to: 'https://evil.example/steal' },
  })

  assert.equal(response.headers.location, `/admin/customers/${customerId}`)
})

test('POST /admin/customers/:id/blocks refuses a customer who is already blocked', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'First.' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Second.' },
  })

  assert.equal(response.statusCode, 302)
  const flash = flashFrom(testApp, response)
  assert.match(flash.alert ?? '', /already blocked/)
})

test('POST /admin/customers/:id/blocks/lift restores shopping', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks/lift`,
    cookies: admin.cookies,
    payload: {},
  })

  assert.equal(response.statusCode, 302)
  const standing = await currentCustomerStanding({ db: testApp.db }, customerId)
  assert.equal(standing.isBlocked, false)
  assert.equal(canShop(standing), true)
})

test('POST /admin/customers/:id/blocks/lift refuses a customer who is not blocked', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks/lift`,
    cookies: admin.cookies,
    payload: {},
  })

  const flash = flashFrom(testApp, response)
  assert.match(flash.alert ?? '', /not blocked/)
})

test('a reason that is blank after trimming is rejected before the action runs', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: '   ' },
  })

  assert.notEqual(response.statusCode, 302)
  const standing = await currentCustomerStanding({ db: testApp.db }, customerId)
  assert.equal(standing.isBlocked, false)
})

test('POST /admin/listings/:id/removals removes the listing and takes it off the storefront', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, {
    title: 'Harbour at Dusk',
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'temporary', reason: 'Reported artwork.' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/admin/listings/${listing.id}`)

  const removal = await activeListingRemoval({ db: testApp.db }, listing.id)
  assert.notEqual(removal, null)
  assert.equal(removal?.kind, 'temporary')
  assert.equal(isOnStorefront('for_sale', removal !== null), false)

  const storefront = await testApp.app.inject({ method: 'GET', url: `/art/${listing.slug}` })
  assert.equal(storefront.statusCode, 404)
})

test('POST /admin/listings/:id/removals honors a redirect_to on the same origin', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'temporary', reason: 'Reported.', redirect_to: '/admin/listings' },
  })

  assert.equal(response.headers.location, '/admin/listings')
})

test('POST /admin/listings/:id/removals refuses a listing that is already removed', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'temporary', reason: 'First.' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'permanent', reason: 'Second.' },
  })

  assert.equal(response.statusCode, 302)
  const flash = flashFrom(testApp, response)
  assert.match(flash.alert ?? '', /already removed/)
})

test('POST /admin/listings/:id/removals/lift restores a temporary removal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'temporary', reason: 'Reported.' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals/lift`,
    cookies: admin.cookies,
    payload: {},
  })

  assert.equal(response.statusCode, 302)
  assert.equal(await activeListingRemoval({ db: testApp.db }, listing.id), null)

  const storefront = await testApp.app.inject({ method: 'GET', url: `/art/${listing.slug}` })
  assert.equal(storefront.statusCode, 200)
})

test('POST /admin/listings/:id/removals/lift refuses a permanent removal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'permanent', reason: 'Counterfeit.' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals/lift`,
    cookies: admin.cookies,
    payload: {},
  })

  const flash = flashFrom(testApp, response)
  assert.match(flash.alert ?? '', /cannot be lifted/)
  assert.notEqual(await activeListingRemoval({ db: testApp.db }, listing.id), null)
})

test('POST /admin/listings/:id/removals/lift refuses a listing that is not removed', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals/lift`,
    cookies: admin.cookies,
    payload: {},
  })

  const flash = flashFrom(testApp, response)
  assert.match(flash.alert ?? '', /not removed/)
})

test('a bodiless POST to lift a temporary removal still lifts it', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'temporary', reason: 'Reported.' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals/lift`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(await activeListingRemoval({ db: testApp.db }, listing.id), null)
})

test('a bodiless POST to lift a block still lifts it', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks/lift`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 302)
  const standing = await currentCustomerStanding({ db: testApp.db }, customerId)
  assert.equal(standing.isBlocked, false)
  assert.equal(canShop(standing), true)
})

test('a bodiless POST to remove a listing answers 400', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
  assert.equal(await activeListingRemoval({ db: testApp.db }, listing.id), null)
})

test('a bodiless POST to block a customer answers 400', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
  const standing = await currentCustomerStanding({ db: testApp.db }, customerId)
  assert.equal(standing.isBlocked, false)
})

test('a POST to block a customer naming only redirect_to answers 400', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { redirect_to: '/admin/customers' },
  })

  assert.equal(response.statusCode, 400)
  const standing = await currentCustomerStanding({ db: testApp.db }, customerId)
  assert.equal(standing.isBlocked, false)
})

test('a blocked customer is turned away from checkout on the storefront', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const context = { db: testApp.db, clock: testApp.clock }
  const admin = await signInAsAdmin(testApp)
  const shopper = await signInAsCustomer(testApp)
  const listing = await createListing(context, await createSeller(context))
  await cartHolding(context, shopper.id, [listing.id])

  const beforeBlock = await testApp.app.inject({
    method: 'GET',
    url: '/checkout',
    cookies: shopper.cookies,
  })

  assert.equal(beforeBlock.statusCode, 200)

  await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${shopper.id}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.' },
  })

  const afterBlock = await testApp.app.inject({
    method: 'GET',
    url: '/checkout',
    cookies: shopper.cookies,
  })

  assert.equal(afterBlock.statusCode, 302)
  assert.equal(afterBlock.headers.location, '/cart')
  assert.match(flashFrom(testApp, afterBlock).alert ?? '', /Chargeback fraud\./)
})

test('the four moderation writes each log a business event with the admin who did it', async (t) => {
  const stream = captureLogLines()
  const testApp = await buildTestApp({
    config: { ...TEST_CONFIG, logLevel: 'info' },
    loggerStream: stream,
  })
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })

  await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals`,
    cookies: admin.cookies,
    payload: { kind: 'temporary', reason: 'Reported artwork.' },
  })
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/listings/${listing.id}/removals/lift`,
    cookies: admin.cookies,
  })
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.' },
  })
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/blocks/lift`,
    cookies: admin.cookies,
  })

  const lines = stream.lines()

  const removed = eventLine(lines, 'moderation.listing_removed')
  assert.equal(removed.listingId, listing.id)
  assert.equal(removed.adminId, admin.id)
  assert.equal(removed.reason, 'Reported artwork.')

  const lifted = eventLine(lines, 'moderation.listing_removal_lifted')
  assert.equal(lifted.listingId, listing.id)
  assert.equal(lifted.adminId, admin.id)

  const blocked = eventLine(lines, 'moderation.customer_blocked')
  assert.equal(blocked.customerId, customerId)
  assert.equal(blocked.adminId, admin.id)
  assert.equal(blocked.reason, 'Chargeback fraud.')

  const unblocked = eventLine(lines, 'moderation.customer_block_lifted')
  assert.equal(unblocked.customerId, customerId)
  assert.equal(unblocked.adminId, admin.id)
})

/** The one line logging the named event, so an assertion on its fields never
 * needs to guard against the line being absent. */
function eventLine(lines: readonly Record<string, unknown>[], event: string): Record<string, unknown> {
  const line = lines.find((candidate) => candidate.event === event)
  if (line === undefined) throw new Error(`no log line carries event ${event}`)

  return line
}

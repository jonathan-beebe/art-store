import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin, signInAsCustomer, type TestApp } from '../../../test/build-test-app.ts'
import { activeListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import { currentCustomerStanding } from '../../../actions/moderation/current-customer-standing.ts'
import { isOnStorefront } from '../../../core/listings/listing-availability.ts'
import { canShop } from '../../../core/moderation/customer-standing.ts'
import { cartHolding, createListing, createCustomer, createSeller } from '../../../test/commerce-world.ts'
import { buildLoggedTestApp } from '../../../test/log-lines.ts'

function flashFrom(testApp: TestApp, response: { cookies: { name: string; value: string }[] }): Record<string, string> {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')
  if (cookie === undefined) return {}

  const unsigned = testApp.app.unsignCookie(cookie.value)

  return JSON.parse(unsigned.value ?? '{}') as Record<string, string>
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
  assert.equal(flash.alert, 'This customer is already blocked.')
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
  assert.equal(flash.alert, 'This customer is not blocked.')
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
  assert.equal(flash.alert, 'This listing is already removed.')
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
  assert.equal(flash.alert, 'A permanent removal cannot be lifted.')
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
  assert.equal(flash.alert, 'This listing is not removed.')
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

test('the four moderation writes each tell their story naming the admin who did it', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
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

  for (const event of MODERATION_EVENTS) {
    assert.equal(log.line(event, 'will').actor_id, admin.id, event)
    assert.equal(log.line(event, 'did').actor_type, 'admin', event)
  }

  assert.equal(log.data('moderation.remove_listing', 'did').listing_id, listing.id)
  assert.equal(log.data('moderation.remove_listing', 'did').admin_id, admin.id)
  assert.equal(log.data('moderation.lift_listing_removal', 'did').listing_id, listing.id)
  assert.equal(log.data('moderation.block_customer', 'did').customer_id, customerId)
  assert.equal(log.data('moderation.lift_customer_block', 'did').customer_id, customerId)

  // The reason a moderator typed is theirs and the subject's; the log names who
  // and what, and the row keeps the words.
  assert.equal(log.text().includes('Chargeback fraud.'), false)
})

test('a moderation write the domain refuses is refused rather than failed', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const block = {
    method: 'POST' as const,
    url: `/admin/customers/${customerId}/blocks`,
    cookies: admin.cookies,
    payload: { reason: 'Chargeback fraud.' },
  }

  await testApp.app.inject(block)
  await testApp.app.inject(block)

  const refused = log.line('moderation.block_customer', 'refused')
  assert.equal(refused.level, 'info')
  assert.equal(typeof refused.duration_ms, 'number')
  assert.equal((refused.data as { reason?: string }).reason, 'already_blocked')
  assert.equal((refused.data as { customer_id?: string }).customer_id, customerId)
  assert.equal(
    log.linesFor('moderation.block_customer').some((line: Record<string, unknown>) => line.phase === 'failed'),
    false,
  )
})

const MODERATION_EVENTS = [
  'moderation.remove_listing',
  'moderation.lift_listing_removal',
  'moderation.block_customer',
  'moderation.lift_customer_block',
] as const

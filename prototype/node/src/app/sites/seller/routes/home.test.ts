import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsSeller } from '../../../test/build-test-app.ts'
import { createFulfillment, createTestListing, createTestNotification } from '../test-fixtures.ts'

test('a signed-out visitor is sent to the sign-in page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller' })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/seller/login?redirect_to=%2Fseller')
})

test('the dashboard renders in the seller layout', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /<title>Dashboard — Seller portal<\/title>/)
  assert.match(response.body, /href="\/seller\/listings"/)
})

test("it tallies the seller's listings by status", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createTestListing(testApp, seller.id)
  await createTestListing(testApp, seller.id, { title: 'Second Piece' })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })

  assert.match(response.body, /data-stat="draft"[^>]*>\s*2\s*</)
  assert.match(response.body, /data-stat="for_sale"[^>]*>\s*0\s*</)
})

test("another seller's listings are counted on their own dashboard", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  await createTestListing(testApp, rival.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })

  assert.match(response.body, /data-stat="draft"[^>]*>\s*0\s*</)
})

test('it counts fulfillments waiting to be shipped and shows the escrow balance', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })

  assert.match(response.body, /data-stat="awaiting_shipment"[^>]*>\s*1\s*</)
  assert.match(response.body, /data-stat="held"[^>]*>\s*\$405\.00\s*</)
  assert.match(response.body, /data-stat="available"[^>]*>\s*\$0\.00\s*</)
})

test('it counts unread notifications and lists the most recent five', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  for (let index = 0; index < 6; index += 1) {
    await createTestNotification(testApp, seller.id, { subject: `Notice ${index}` })
  }
  await createTestNotification(testApp, seller.id, { subject: 'Read one', read: true })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })

  assert.match(response.body, /data-stat="unread_notifications"[^>]*>\s*6\s*</)
  assert.equal((response.body.match(/data-recent-notification/g) ?? []).length, 5)
  assert.match(response.body, /Read one/)
})

test("another seller's notifications stay off this dashboard", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  await createTestNotification(testApp, rival.id, { subject: 'Rival notice' })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })

  assert.match(response.body, /data-stat="unread_notifications"[^>]*>\s*0\s*</)
  assert.doesNotMatch(response.body, /Rival notice/)
})

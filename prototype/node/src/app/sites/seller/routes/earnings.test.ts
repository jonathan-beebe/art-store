import { test } from 'node:test'
import assert from 'node:assert/strict'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import { payoutPeriodEndingBefore, payoutPeriodEndsAt } from '../../../core/escrow/payout-period.ts'
import { toTimestamp } from '../../../db/timestamp.ts'
import { buildTestApp, signInAsSeller, TEST_INSTANT } from '../../../test/build-test-app.ts'
import { createDeliveredFulfillment, createForSaleListing, createFulfillment } from '../test-fixtures.ts'

test('a signed-out visitor sees no earnings', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/earnings' })

  assert.equal(response.statusCode, 302)
})

test('a sale waiting on delivery is held in escrow', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-stat="held"[^>]*>\s*\$405\.00\s*</)
  assert.match(response.body, /data-stat="available"[^>]*>\s*\$0\.00\s*</)
  assert.match(response.body, /data-stat="paid_out"[^>]*>\s*\$0\.00\s*</)
})

test('a delivered sale is available to pay out', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createDeliveredFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })

  assert.match(response.body, /data-stat="held"[^>]*>\s*\$0\.00\s*</)
  assert.match(response.body, /data-stat="available"[^>]*>\s*\$405\.00\s*</)
})

test('each sale carries its subtotal, fee, net, and status', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id, { title: 'Harbour at Dusk' })
  const fulfillment = await createFulfillment(testApp, seller.id, listing)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })

  const row = new RegExp(
    `data-fulfillment="${fulfillment.id}"[\\s\\S]*?Harbour at Dusk[\\s\\S]*?` +
      'data-cell="subtotal"[^>]*>\\s*\\$450\\.00\\s*<[\\s\\S]*?' +
      'data-cell="fee"[^>]*>\\s*\\$45\\.00\\s*<[\\s\\S]*?' +
      'data-cell="net"[^>]*>\\s*\\$405\\.00\\s*<[\\s\\S]*?' +
      'data-cell="status"[^>]*>Awaiting shipment<',
  )
  assert.match(response.body, row)
})

test("another seller's sales and balances stay off the page", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalFulfillment = await createFulfillment(testApp, rival.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })

  assert.doesNotMatch(response.body, new RegExp(`data-fulfillment="${rivalFulfillment.id}"`))
  assert.match(response.body, /data-stat="held"[^>]*>\s*\$0\.00\s*</)
  assert.match(response.body, /No sales yet\./)
})

test('a settled week shows up in the payouts table', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createDeliveredFulfillment(testApp, seller.id)
  const period = payoutPeriodEndingBefore(TEST_INSTANT)
  await testApp.db
    .updateTable('ledgerEntries')
    .set({ occurredAt: toTimestamp(new Date(payoutPeriodEndsAt(period).getTime() - 1000)) })
    .execute()
  await runWeeklyPayout({ db: testApp.db, clock: testApp.clock }, TEST_INSTANT)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })

  assert.match(response.body, /data-payout[\s\S]*?data-cell="amount"[^>]*>\s*\$405\.00\s*</)
  assert.match(response.body, /data-stat="paid_out"[^>]*>\s*\$405\.00\s*</)
  assert.match(response.body, /data-stat="available"[^>]*>\s*\$0\.00\s*</)
})

test('there is no payout button on the page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createDeliveredFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })

  assert.doesNotMatch(response.body, /action="[^"]*payout[^"]*"/)
  assert.doesNotMatch(response.body, /Run.*payout/i)
})

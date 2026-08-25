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

test('the sales and movements tables page independently at 25', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillments = []
  for (let i = 0; i < 26; i += 1) {
    fulfillments.push(await createFulfillment(testApp, seller.id))
  }
  const newestFulfillment = fulfillments.at(-1)!
  const oldestFulfillment = fulfillments[0]!

  const firstPage = await testApp.app.inject({ method: 'GET', url: '/seller/earnings', cookies: seller.cookies })

  assert.equal(firstPage.statusCode, 200)
  assert.equal((firstPage.body.match(/data-fulfillment="/g) ?? []).length, 25)
  assert.equal((firstPage.body.match(/data-movement="/g) ?? []).length, 25)
  assert.match(firstPage.body, new RegExp(`data-fulfillment="${newestFulfillment.id}"`))
  assert.doesNotMatch(firstPage.body, new RegExp(`data-fulfillment="${oldestFulfillment.id}"`))
  assert.match(firstPage.body, /href="\/seller\/earnings\?movements_page=1&amp;sales_page=2"/)
  assert.match(firstPage.body, /href="\/seller\/earnings\?sales_page=1&amp;movements_page=2"/)

  const secondSalesPage = await testApp.app.inject({
    method: 'GET',
    url: '/seller/earnings?sales_page=2',
    cookies: seller.cookies,
  })

  assert.equal((secondSalesPage.body.match(/data-fulfillment="/g) ?? []).length, 1)
  assert.match(secondSalesPage.body, new RegExp(`data-fulfillment="${oldestFulfillment.id}"`))
  // The movements table stays on page one while only the sales page moved.
  assert.equal((secondSalesPage.body.match(/data-movement="/g) ?? []).length, 25)

  const secondMovementsPage = await testApp.app.inject({
    method: 'GET',
    url: '/seller/earnings?movements_page=2',
    cookies: seller.cookies,
  })

  assert.equal((secondMovementsPage.body.match(/data-movement="/g) ?? []).length, 1)
  assert.equal((secondMovementsPage.body.match(/data-fulfillment="/g) ?? []).length, 25)
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

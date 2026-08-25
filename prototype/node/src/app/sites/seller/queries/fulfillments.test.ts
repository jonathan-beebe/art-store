import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsSeller } from '../../../test/build-test-app.ts'
import { createDeliveredFulfillment, createForSaleListing, createFulfillment } from '../test-fixtures.ts'
import { fulfillmentCountsByStatus, fulfillmentsForSeller, itemTitlesByOrder } from './fulfillments.ts'

test('fulfillmentsForSeller pages newest first, offset and limit honoured', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const first = await createFulfillment(testApp, seller.id)
  const second = await createFulfillment(testApp, seller.id)
  const third = await createFulfillment(testApp, seller.id)

  const firstPage = await fulfillmentsForSeller(testApp.db, seller.id, { offset: 0, limit: 2 })
  const secondPage = await fulfillmentsForSeller(testApp.db, seller.id, { offset: 2, limit: 2 })

  assert.deepEqual(
    firstPage.map((fulfillment) => fulfillment.id),
    [third.id, second.id],
  )
  assert.deepEqual(
    secondPage.map((fulfillment) => fulfillment.id),
    [first.id],
  )
})

test("fulfillmentsForSeller excludes another seller's fulfillments", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  await createFulfillment(testApp, rival.id)
  const owned = await createFulfillment(testApp, seller.id)

  const page = await fulfillmentsForSeller(testApp.db, seller.id, { offset: 0, limit: 25 })

  assert.deepEqual(
    page.map((fulfillment) => fulfillment.id),
    [owned.id],
  )
})

test('fulfillmentCountsByStatus counts every fulfillment regardless of page size', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createFulfillment(testApp, seller.id)
  await createFulfillment(testApp, seller.id)
  await createDeliveredFulfillment(testApp, seller.id)

  const counts = await fulfillmentCountsByStatus(testApp.db, seller.id)

  assert.equal(counts.get('awaiting_shipment'), 2)
  assert.equal(counts.get('delivered'), 1)
  assert.equal(counts.get('shipped'), undefined)
})

test("fulfillmentCountsByStatus excludes another seller's fulfillments", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  await createFulfillment(testApp, rival.id)

  const counts = await fulfillmentCountsByStatus(testApp.db, seller.id)

  assert.equal(counts.size, 0)
})

test('itemTitlesByOrder rolls up every item title under its order', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id, { title: 'Harbour at Dusk', quantity: 2 })
  const fulfillment = await createFulfillment(testApp, seller.id, listing)

  const titles = await itemTitlesByOrder(testApp.db, [fulfillment.orderId], seller.id)

  assert.deepEqual(titles.get(fulfillment.orderId), ['Harbour at Dusk'])
})

test('itemTitlesByOrder returns an empty map for no order ids', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const titles = await itemTitlesByOrder(testApp.db, [], seller.id)

  assert.equal(titles.size, 0)
})

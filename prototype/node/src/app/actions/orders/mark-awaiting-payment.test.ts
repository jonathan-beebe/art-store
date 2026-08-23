import { test } from 'node:test'
import assert from 'node:assert/strict'
import { markAwaitingPayment } from './mark-awaiting-payment.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld, placedOrder } from '../../test/commerce-world.ts'

test('verifying opens payment on a guest order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const guest = await createCustomer(context, { isVerified: false })
  const art = await createListing(context, shop)
  const order = await placedOrder(context, guest, [art.id], { isVerified: false })

  const opened = await markAwaitingPayment(context, order.id)

  assert.equal(opened.status, 'awaiting_payment')
})

test('an order that already awaits payment stays where it is', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])

  await markAwaitingPayment(context, order.id)
  const stillWaiting = await markAwaitingPayment(context, order.id)

  assert.equal(stillWaiting.status, 'awaiting_payment')
})

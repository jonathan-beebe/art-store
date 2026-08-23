import { test } from 'node:test'
import assert from 'node:assert/strict'
import { notify } from './notify.ts'
import { itemSoldMessage } from '../../core/notifications/notification-message.ts'
import type { DeliverableNotification } from '../../core/notifications/notification-delivery.ts'
import { createAdmin, createCustomer, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('it files a seller message under sellerId with the other two recipient columns null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  const notification = await notify(context, {
    recipientType: 'seller',
    recipientId: shop,
    message: itemSoldMessage(7, 40_500),
  })

  assert.equal(notification.sellerId, shop)
  assert.equal(notification.customerId, null)
  assert.equal(notification.adminId, null)
})

test('it files a customer message under customerId', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const buyer = await createCustomer(context)

  const notification = await notify(context, {
    recipientType: 'customer',
    recipientId: buyer,
    message: itemSoldMessage(7, 40_500),
  })

  assert.equal(notification.customerId, buyer)
  assert.equal(notification.sellerId, null)
  assert.equal(notification.adminId, null)
})

test('it files an admin message under adminId', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const admin = await createAdmin(context)

  const notification = await notify(context, {
    recipientType: 'admin',
    recipientId: admin,
    message: itemSoldMessage(7, 40_500),
  })

  assert.equal(notification.adminId, admin)
  assert.equal(notification.sellerId, null)
  assert.equal(notification.customerId, null)
})

test('a new notification is unread', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  const notification = await notify(context, {
    recipientType: 'seller',
    recipientId: shop,
    message: itemSoldMessage(7, 40_500),
  })

  assert.equal(notification.readAt, null)
})

test('it carries the url the message points at', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  const notification = await notify(context, {
    recipientType: 'seller',
    recipientId: shop,
    message: itemSoldMessage(7, 40_500, '/seller/orders/7'),
  })

  assert.equal(notification.url, '/seller/orders/7')
})

test('the notificationDelivery port receives the notification when one is on the context', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const delivered: DeliverableNotification[] = []
  const notificationDelivery = { deliver: async (n: DeliverableNotification) => { delivered.push(n) } }

  await notify(
    { ...context, notificationDelivery },
    { recipientType: 'seller', recipientId: shop, message: itemSoldMessage(7, 40_500) },
  )

  assert.equal(delivered.length, 1)
  assert.equal(delivered[0]?.recipientType, 'seller')
  assert.equal(delivered[0]?.recipientId, shop)
})

test('a delivery that fails leaves the notification on file', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const notificationDelivery = {
    deliver: async () => {
      throw new Error('mail is down')
    },
  }

  await assert.rejects(() =>
    notify(
      { ...context, notificationDelivery },
      { recipientType: 'seller', recipientId: shop, message: itemSoldMessage(7, 40_500) },
    ),
  )

  const filed = await context.db.selectFrom('notifications').selectAll().execute()

  assert.equal(filed.length, 1)
})

test('notify works with none', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  await assert.doesNotReject(() =>
    notify(context, { recipientType: 'seller', recipientId: shop, message: itemSoldMessage(7, 40_500) }),
  )
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { notify } from './notify.ts'
import { itemSoldMessage } from '../../core/notifications/notification-message.ts'
import type { DeliveryContext } from '../../delivery/delivery-context.ts'
import type { DeliverableNotification } from '../../delivery/notification-delivery.ts'
import { createAdmin, createCustomer, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'
import { cents } from '../../core/money.ts'

test('it files a seller message under sellerId with the other two recipient columns null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  const notification = await notify(context, {
    recipientType: 'seller',
    recipientId: shop,
    message: itemSoldMessage(7, cents(40_500)),
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
    message: itemSoldMessage(7, cents(40_500)),
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
    message: itemSoldMessage(7, cents(40_500)),
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
    message: itemSoldMessage(7, cents(40_500)),
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
    message: itemSoldMessage(7, cents(40_500), '/seller/orders/7'),
  })

  assert.equal(notification.url, '/seller/orders/7')
})

test('the notificationDelivery port receives the notification when one is on the context', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const delivered: DeliverableNotification[] = []
  const notificationDelivery = {
    deliver: async (_context: DeliveryContext, n: DeliverableNotification) => {
      delivered.push(n)
    },
  }

  await notify(
    { ...context, notificationDelivery },
    { recipientType: 'seller', recipientId: shop, message: itemSoldMessage(7, cents(40_500)) },
  )

  assert.equal(delivered.length, 1)
  assert.equal(delivered[0]?.recipientType, 'seller')
  assert.equal(delivered[0]?.recipientId, shop)
})

test('a delivery that fails takes the notification down with it', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const notificationDelivery = {
    deliver: () => Promise.reject(new Error('mail is down')),
  }

  await assert.rejects(() =>
    notify(
      { ...context, notificationDelivery },
      { recipientType: 'seller', recipientId: shop, message: itemSoldMessage(7, cents(40_500)) },
    ),
  )

  const filed = await context.db.selectFrom('notifications').selectAll().execute()

  assert.equal(filed.length, 0)
})

test('with no delivery on the context the message is queued for the outbox', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  await notify(context, {
    recipientType: 'seller',
    recipientId: shop,
    message: itemSoldMessage(7, cents(40_500), '/seller/orders/7'),
  })

  const queued = await context.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.equal(queued.subject, 'Item sold')
  assert.equal(queued.url, '/seller/orders/7')
  assert.equal(queued.deliveredAt, null)
})

test('a business transaction that rolls back leaves neither the inbox row nor the outbox row', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  await assert.rejects(() =>
    world.db.transaction().execute(async (transaction) => {
      await notify(
        { ...context, db: transaction },
        { recipientType: 'seller', recipientId: shop, message: itemSoldMessage(7, cents(40_500)) },
      )
      throw new Error('the sale fell through after the seller was told')
    }),
  )

  assert.deepEqual(await context.db.selectFrom('notifications').selectAll().execute(), [])
  assert.deepEqual(await context.db.selectFrom('outboxMessages').selectAll().execute(), [])
})

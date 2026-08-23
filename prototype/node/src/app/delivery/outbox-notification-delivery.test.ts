import { test } from 'node:test'
import assert from 'node:assert/strict'
import { outboxNotificationDelivery } from './outbox-notification-delivery.ts'
import { createAdmin, createCustomer, createSeller, openCommerceWorld } from '../test/commerce-world.ts'

const SOLD = {
  subject: 'Item sold',
  body: 'Order #7 is paid.',
  url: '/seller/orders/7',
}

test('a seller notification is addressed to the seller’s own address', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const seller = await world.db
    .selectFrom('sellers')
    .select('email')
    .where('id', '=', sellerId)
    .executeTakeFirstOrThrow()

  await outboxNotificationDelivery.deliver(world.context, {
    recipientType: 'seller',
    recipientId: sellerId,
    ...SOLD,
  })

  const queued = await world.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.equal(queued.recipient, seller.email)
  assert.equal(queued.subject, 'Item sold')
  assert.equal(queued.url, '/seller/orders/7')
})

test('a customer notification is addressed to the customer’s address', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)

  await outboxNotificationDelivery.deliver(world.context, {
    recipientType: 'customer',
    recipientId: customerId,
    ...SOLD,
  })

  const queued = await world.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.match(queued.recipient, /^customer-\d+@example\.test$/)
})

test('an admin notification is addressed to the admin’s address', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)

  await outboxNotificationDelivery.deliver(world.context, {
    recipientType: 'admin',
    recipientId: adminId,
    ...SOLD,
  })

  const queued = await world.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.match(queued.recipient, /^admin-\d+@example\.test$/)
})

test('a customer who has given no address queues nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context, { isVerified: false })

  await outboxNotificationDelivery.deliver(world.context, {
    recipientType: 'customer',
    recipientId: customerId,
    ...SOLD,
  })

  const queued = await world.db.selectFrom('outboxMessages').selectAll().execute()
  assert.equal(queued.length, 0)
})

test('a recipient id naming nobody queues nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await outboxNotificationDelivery.deliver(world.context, {
    recipientType: 'seller',
    recipientId: 9_999,
    ...SOLD,
  })

  const queued = await world.db.selectFrom('outboxMessages').selectAll().execute()
  assert.equal(queued.length, 0)
})

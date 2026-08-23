import { test } from 'node:test'
import assert from 'node:assert/strict'
import { currentCustomerStanding } from './current-customer-standing.ts'
import { canShop } from '../../core/moderation/customer-standing.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { createAdmin, createCustomer, openCommerceWorld } from '../../test/commerce-world.ts'

test('a customer with no blocks is not blocked and can shop', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)

  const standing = await currentCustomerStanding(context, customerId)

  assert.equal(standing.isBlocked, false)
  assert.equal(canShop(standing), true)
})

test('an unlifted block blocks them and carries the reason', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const customerId = await createCustomer(context)
  const adminId = await createAdmin(context)
  await db
    .insertInto('customerBlocks')
    .values({
      customerId,
      adminId,
      reason: 'Chargeback abuse',
      createdAt: toTimestamp(new Date('2026-08-20T09:00:00.000Z')),
      liftedAt: null,
    })
    .execute()

  const standing = await currentCustomerStanding(context, customerId)

  assert.equal(standing.isBlocked, true)
  assert.equal(standing.reason, 'Chargeback abuse')
  assert.equal(canShop(standing), false)
})

test('a lifted block leaves them alone', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const customerId = await createCustomer(context)
  const adminId = await createAdmin(context)
  await db
    .insertInto('customerBlocks')
    .values({
      customerId,
      adminId,
      reason: 'Chargeback abuse',
      createdAt: toTimestamp(new Date('2026-08-20T09:00:00.000Z')),
      liftedAt: toTimestamp(new Date('2026-08-21T09:00:00.000Z')),
    })
    .execute()

  const standing = await currentCustomerStanding(context, customerId)

  assert.equal(standing.isBlocked, false)
  assert.equal(canShop(standing), true)
})

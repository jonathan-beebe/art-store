import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeCustomerBlock } from './active-customer-block.ts'
import { blockCustomer } from './block-customer.ts'
import { liftCustomerBlock } from './lift-customer-block.ts'
import { createAdmin, createCustomer, openCommerceWorld } from '../../test/commerce-world.ts'

test('the active block carries its reason and when it was issued', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)
  const customerId = await createCustomer(world.context)

  await blockCustomer(world.context, { customerId, adminId, reason: 'Chargeback fraud.' })

  const active = await activeCustomerBlock(world.context, customerId)

  assert.equal(active?.reason, 'Chargeback fraud.')
  assert.deepEqual(active?.createdAt, new Date('2026-08-20T09:00:00.000Z'))
  assert.equal(active?.liftedAt, null)
})

test('a customer with no block, and one whose block was lifted, have none', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)
  const customerId = await createCustomer(world.context)

  assert.equal(await activeCustomerBlock(world.context, customerId), null)

  await blockCustomer(world.context, { customerId, adminId, reason: 'Chargeback fraud.' })
  await liftCustomerBlock(world.context, { customerId })

  assert.equal(await activeCustomerBlock(world.context, customerId), null)
})

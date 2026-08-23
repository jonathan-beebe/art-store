import { test } from 'node:test'
import assert from 'node:assert/strict'
import { blockCustomer } from './block-customer.ts'
import { currentCustomerStanding } from './current-customer-standing.ts'
import { liftCustomerBlock } from './lift-customer-block.ts'
import { canShop } from '../../core/moderation/customer-standing.ts'
import { TransitionError } from '../../core/transition-error.ts'
import { createAdmin, createCustomer, openCommerceWorld } from '../../test/commerce-world.ts'

test('lifting a block hands the cart and checkout back', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)
  const customerId = await createCustomer(world.context)

  await blockCustomer(world.context, { customerId, adminId, reason: 'Chargeback fraud.' })

  world.travelTo(new Date('2026-08-21T09:00:00.000Z'))
  const lifted = await liftCustomerBlock(world.context, { customerId })

  assert.equal(lifted.liftedAt, '2026-08-21T09:00:00.000Z')
  assert.equal(canShop(await currentCustomerStanding(world.context, customerId)), true)
})

test('a customer nobody blocked cannot be lifted', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)

  await assert.rejects(() => liftCustomerBlock(world.context, { customerId }), TransitionError)
})

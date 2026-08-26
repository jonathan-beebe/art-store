import { test } from 'node:test'
import assert from 'node:assert/strict'
import { blockCustomer } from './block-customer.ts'
import { currentCustomerStanding } from './current-customer-standing.ts'
import { liftCustomerBlock } from './lift-customer-block.ts'
import { canShop } from '../../core/moderation/customer-standing.ts'
import { mustSucceed } from '../../core/refusal.ts'
import { createAdmin, createCustomer, openCommerceWorld } from '../../test/commerce-world.ts'

test('a blocked customer keeps browsing and loses shopping', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)
  const customerId = await createCustomer(world.context)

  const block = mustSucceed(
    await blockCustomer(world.context, {
      customerId,
      adminId,
      reason: 'Chargeback fraud.',
    }),
  ).block

  assert.equal(block.adminId, adminId)
  assert.equal(block.liftedAt, null)

  const standing = await currentCustomerStanding(world.context, customerId)

  assert.deepEqual(standing, { isBlocked: true, reason: 'Chargeback fraud.' })
  assert.equal(canShop(standing), false)
})

test('a customer already blocked is not blocked a second time', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)
  const customerId = await createCustomer(world.context)

  const first = mustSucceed(
    await blockCustomer(world.context, { customerId, adminId, reason: 'Chargeback fraud.' }),
  ).block

  const result = await blockCustomer(world.context, { customerId, adminId, reason: 'Again.' })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'already_blocked',
    data: { customer_id: customerId, customer_block_id: first.id },
  })

  const blocks = await world.db.selectFrom('customerBlocks').selectAll().execute()

  assert.equal(blocks.length, 1)
})

test('a lifted block leaves the customer blockable again, on a new reason', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)
  const customerId = await createCustomer(world.context)

  mustSucceed(await blockCustomer(world.context, { customerId, adminId, reason: 'Chargeback fraud.' }))
  mustSucceed(await liftCustomerBlock(world.context, { customerId }))
  mustSucceed(await blockCustomer(world.context, { customerId, adminId, reason: 'It happened again.' }))

  assert.deepEqual(await currentCustomerStanding(world.context, customerId), {
    isBlocked: true,
    reason: 'It happened again.',
  })
})

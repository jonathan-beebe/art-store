import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openSupportConversation } from './open-support-conversation.ts'
import { claimSellerIdentity } from '../auth/claim-seller-identity.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import { findAdminByEmail } from '../auth/find-admin-by-email.ts'
import type { ActionContext } from '../action-context.ts'
import { fixedClock } from '../../clock.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../../db/database.ts'
import { seedAdmins } from '../../db/seed-admins.ts'
import { migrateToLatest } from '../../db/migrator.ts'

const NOW = new Date('2026-08-22T10:00:00.000Z')

async function openWorld(): Promise<{ db: AppDatabase; context: ActionContext; close: () => Promise<void> }> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)
  return { db, context: { db, clock: fixedClock(NOW) }, close: () => db.destroy() }
}

async function seller(context: ActionContext, email = 'shop@example.test') {
  return claimSellerIdentity(context, email)
}

async function customer(context: ActionContext, email = 'buyer@example.test') {
  return claimCustomerIdentity(context, { email, currentCustomerId: null })
}

async function admin(context: ActionContext) {
  await seedAdmins(context)
  const found = await findAdminByEmail(context, 'jonathan-beebe@outlook.com')
  if (found === null) throw new Error('admin not seeded')
  return found
}

test('a seller opens a support thread with the first admin by id', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const support = await admin(world.context)
  const shop = await seller(world.context)

  const result = await openSupportConversation(world.context, { actorType: 'seller', actorId: shop.id })

  assert.equal(result.outcome, 'opened')
  if (result.outcome !== 'opened') return
  assert.equal(result.conversation.kind, 'admin_seller')
  assert.equal(result.conversation.sellerId, shop.id)
  assert.equal(result.conversation.adminId, support.id)
})

test('a customer opens a support thread with the first admin by id', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const support = await admin(world.context)
  const buyer = await customer(world.context)

  const result = await openSupportConversation(world.context, { actorType: 'customer', actorId: buyer.id })

  assert.equal(result.outcome, 'opened')
  if (result.outcome !== 'opened') return
  assert.equal(result.conversation.kind, 'admin_customer')
  assert.equal(result.conversation.customerId, buyer.id)
  assert.equal(result.conversation.adminId, support.id)
})

test('a second call reuses the same thread', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  await admin(world.context)
  const shop = await seller(world.context)

  const first = await openSupportConversation(world.context, { actorType: 'seller', actorId: shop.id })
  const second = await openSupportConversation(world.context, { actorType: 'seller', actorId: shop.id })

  assert.equal(first.outcome, 'opened')
  assert.equal(second.outcome, 'opened')
  if (first.outcome !== 'opened' || second.outcome !== 'opened') return
  assert.equal(first.conversation.id, second.conversation.id)
})

test('with no admin seeded, it answers no-admin rather than opening a half-formed thread', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const shop = await seller(world.context)

  const result = await openSupportConversation(world.context, { actorType: 'seller', actorId: shop.id })

  assert.deepEqual(result, { outcome: 'no-admin' })
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { conversationActor } from './conversation-actor.ts'
import { blockedCustomer, blockCustomer } from '../moderation/block-customer.ts'
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

async function customer(context: ActionContext, email = 'buyer@example.test') {
  return claimCustomerIdentity(context, { email, currentCustomerId: null })
}

async function admin(context: ActionContext) {
  await seedAdmins(context)
  const found = await findAdminByEmail(context, 'jonathan-beebe@outlook.com')
  if (found === null) throw new Error('admin not seeded')
  return found
}

test('a seller carries no isBlocked flag and needs no row for that id', async (t) => {
  const world = await openWorld()
  t.after(world.close)

  const actor = await conversationActor(world.context, { type: 'seller', id: fixtureId('sel', 999) })

  assert.deepEqual(actor, { type: 'seller', id: fixtureId('sel', 999) })
})

test('an admin carries no isBlocked flag and needs no row for that id', async (t) => {
  const world = await openWorld()
  t.after(world.close)

  const actor = await conversationActor(world.context, { type: 'admin', id: fixtureId('adm', 999) })

  assert.deepEqual(actor, { type: 'admin', id: fixtureId('adm', 999) })
})

test('a blocked customer carries isBlocked true', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const support = await admin(world.context)
  const buyer = await customer(world.context)
  blockedCustomer(
    await blockCustomer(world.context, { customerId: buyer.id, adminId: support.id, reason: 'Chargeback fraud.' }),
  )

  const actor = await conversationActor(world.context, { type: 'customer', id: buyer.id })

  assert.equal(actor.type, 'customer')
  assert.equal(actor.type === 'customer' && actor.isBlocked, true)
})

test('an unblocked customer carries isBlocked false', async (t) => {
  const world = await openWorld()
  t.after(world.close)
  const buyer = await customer(world.context)

  const actor = await conversationActor(world.context, { type: 'customer', id: buyer.id })

  assert.equal(actor.type, 'customer')
  assert.equal(actor.type === 'customer' && actor.isBlocked, false)
})

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { newId } from '../../ids.ts'
import { fixedClock } from '../../clock.ts'
import { openDatabase } from '../../db/database.ts'
import { migrateToLatest } from '../../db/migrator.ts'
import { createAnonymousCustomer } from '../customers/create-anonymous-customer.ts'
import { addToCart } from './add-to-cart.ts'
import { currentCart } from './current-cart.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('a visitor with no cart gets one', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)

  const cart = await currentCart(context, customerId)

  assert.equal(cart.customerId, customerId)
})

test('a second call returns the same cart', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)

  const first = await currentCart(context, customerId)
  const second = await currentCart(context, customerId)

  assert.equal(second.id, first.id)
})

test('a customer left with two carts keeps shopping with the one holding items', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const customerId = await createCustomer(context)
  const emptyCart = await currentCart(context, customerId)

  const filledCart = await db
    .insertInto('carts')
    .values({ id: newId('crt', new Date()), customerId, createdAt: toTimestamp(context.clock.now()) })
    .returningAll()
    .executeTakeFirstOrThrow()

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  await addToCart(context, { cartId: filledCart.id, listingId: art.id, quantity: 1 })

  const current = await currentCart(context, customerId)

  assert.equal(current.id, filledCart.id)
  assert.notEqual(current.id, emptyCart.id)
})

test('an existing cart still resolves while a rival connection holds the write lock', async (t) => {
  const directory = await mkdtemp(path.join(tmpdir(), 'current-cart-'))
  const file = path.join(directory, 'db.sqlite')
  const db = openDatabase(file)
  await migrateToLatest(db)
  const clock = fixedClock(new Date('2026-08-24T12:00:00.000Z'))
  const context = { db, clock }

  const customerId = (await createAnonymousCustomer(context)).id
  const opened = await currentCart(context, customerId)

  const rival = new DatabaseSync(file)
  rival.exec('begin immediate')

  t.after(async () => {
    rival.exec('rollback')
    rival.close()
    await db.destroy()
    await rm(directory, { recursive: true, force: true })
  })

  const cart = await currentCart(context, customerId)

  assert.equal(cart.id, opened.id)
})

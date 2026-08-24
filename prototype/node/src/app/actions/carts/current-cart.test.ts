import { test } from 'node:test'
import assert from 'node:assert/strict'
import { newId } from '../../ids.ts'
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

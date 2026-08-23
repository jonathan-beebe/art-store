import { test } from 'node:test'
import assert from 'node:assert/strict'
import { addToCart } from './add-to-cart.ts'
import { currentCart } from './current-cart.ts'
import { removeFromCart } from './remove-from-cart.ts'
import type { AppDatabase } from '../../db/database.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('it takes the listing out of the cart and leaves the others', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const kept = await createListing(context, sellerId)
  const dropped = await createListing(context, sellerId)
  const cart = await currentCart(context, customerId)
  await addToCart(context, { cartId: cart.id, listingId: kept.id, quantity: 1 })
  await addToCart(context, { cartId: cart.id, listingId: dropped.id, quantity: 1 })

  await removeFromCart(context, { cartId: cart.id, listingId: dropped.id })

  assert.deepEqual(await listingIdsIn(world.db, cart.id), [kept.id])
})

test('removing a listing the cart never held changes nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const held = await createListing(context, sellerId)
  const neverHeld = await createListing(context, sellerId)
  const cart = await currentCart(context, customerId)
  await addToCart(context, { cartId: cart.id, listingId: held.id, quantity: 1 })

  await removeFromCart(context, { cartId: cart.id, listingId: neverHeld.id })

  assert.deepEqual(await listingIdsIn(world.db, cart.id), [held.id])
})

async function listingIdsIn(db: AppDatabase, cartId: number): Promise<number[]> {
  const rows = await db
    .selectFrom('cartItems')
    .select('listingId')
    .where('cartId', '=', cartId)
    .orderBy('id')
    .execute()

  return rows.map((row) => row.listingId)
}

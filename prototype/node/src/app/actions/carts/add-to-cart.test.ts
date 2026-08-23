import { test } from 'node:test'
import assert from 'node:assert/strict'
import { addToCart } from './add-to-cart.ts'
import { currentCart } from './current-cart.ts'
import type { AppDatabase } from '../../db/database.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('it puts the listing in the cart', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { quantity: 3 })
  const cart = await currentCart(context, customerId)

  const item = await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 2 })

  assert.equal(item.quantity, 2)
  assert.equal(item.listingId, art.id)
})

test('adding the same listing again adds to the line rather than making a second one', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { quantity: 3 })
  const cart = await currentCart(context, customerId)
  await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 1 })

  await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 1 })

  assert.equal(await countCartItems(world.db, cart.id), 1)
  assert.equal(await lineQuantity(world.db, cart.id, art.id), 2)
})

test('a cart never holds more than the seller has left', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { quantity: 2 })
  const cart = await currentCart(context, customerId)

  const item = await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 5 })

  assert.equal(item.quantity, 2)
})

test('it refuses a sold-out listing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { quantity: 0, status: 'sold' })
  const cart = await currentCart(context, customerId)

  await assert.rejects(
    () => addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 1 }),
    RangeError,
  )
})

test('it records a cart_add listing event against the visitor who added it', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { quantity: 3 })
  const cart = await currentCart(context, customerId)

  await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 1 })

  const events = await world.db
    .selectFrom('listingEvents')
    .select(['eventType', 'customerId'])
    .where('listingId', '=', art.id)
    .execute()

  assert.deepEqual(events, [{ eventType: 'cart_add', customerId }])
})

async function countCartItems(db: AppDatabase, cartId: number): Promise<number> {
  const rows = await db.selectFrom('cartItems').select('id').where('cartId', '=', cartId).execute()
  return rows.length
}

async function lineQuantity(db: AppDatabase, cartId: number, listingId: number): Promise<number | undefined> {
  const row = await db
    .selectFrom('cartItems')
    .select('quantity')
    .where('cartId', '=', cartId)
    .where('listingId', '=', listingId)
    .executeTakeFirst()

  return row?.quantity
}

import { test } from 'node:test'
import assert from 'node:assert/strict'
import { placeOrder } from './place-order.ts'
import { addToCart } from '../carts/add-to-cart.ts'
import { currentCart } from '../carts/current-cart.ts'
import type { AppDatabase } from '../../db/database.ts'
import {
  SHIPPING_ADDRESS,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  placedOrder,
} from '../../test/commerce-world.ts'

test('it turns the cart into an order a verified customer can pay for', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { priceCents: 45_000 })

  const order = await placedOrder(context, buyer, [art.id])

  assert.equal(order.status, 'awaiting_payment')
  assert.equal(order.subtotalCents, 45_000)
  assert.equal(order.totalCents, 45_000)
  assert.equal(order.placedAt, new Date('2026-08-20T09:00:00.000Z').toISOString())
  assert.equal(order.finalizedAt, null)
})

test('a guest places an order that waits for verification', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const guest = await createCustomer(context, { isVerified: false })
  const art = await createListing(context, shop)

  const order = await placedOrder(context, guest, [art.id], { isVerified: false })

  assert.equal(order.status, 'pending_verification')
})

test('it copies the shipping address onto the order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)

  const order = await placedOrder(context, buyer, [art.id])

  assert.equal(order.shippingName, SHIPPING_ADDRESS.name)
  assert.equal(order.shippingLine1, SHIPPING_ADDRESS.line1)
  assert.equal(order.shippingLine2, null)
  assert.equal(order.shippingCity, SHIPPING_ADDRESS.city)
  assert.equal(order.shippingRegion, SHIPPING_ADDRESS.region)
  assert.equal(order.shippingPostalCode, SHIPPING_ADDRESS.postalCode)
  assert.equal(order.shippingCountry, SHIPPING_ADDRESS.country)
})

test('it snapshots the title, unit price, and seller of every item', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { title: 'Harbour at Dusk', priceCents: 45_000 })

  const order = await placedOrder(context, buyer, [art.id])
  const [item] = await readOrderItems(world.db, order.id)

  assert.equal(item?.title, 'Harbour at Dusk')
  assert.equal(item?.unitPriceCents, 45_000)
  assert.equal(item?.sellerId, shop)
})

test('it splits the order into one fulfillment per seller with a 10% fee', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const painter = await createSeller(context, 'Blue Kiln Studio')
  const printer = await createSeller(context, 'Rye Press')
  const buyer = await createCustomer(context)
  const painting = await createListing(context, painter, { priceCents: 45_000 })
  const print = await createListing(context, printer, { priceCents: 10_000 })

  const order = await placedOrder(context, buyer, [painting.id, print.id])

  assert.equal(order.subtotalCents, 55_000)
  assert.deepEqual(await readFulfillments(world.db, order.id), [
    { sellerId: painting.sellerId, status: 'awaiting_shipment', subtotalCents: 45_000, feeCents: 4_500, netCents: 40_500 },
    { sellerId: print.sellerId, status: 'awaiting_shipment', subtotalCents: 10_000, feeCents: 1_000, netCents: 9_000 },
  ])
})

test('it takes the stock the order claims', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { quantity: 3 })

  const cart = await currentCart(context, buyer)
  await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 2 })
  await placeOrder(context, {
    cartId: cart.id,
    purchaser: { id: buyer, email: 'ada@example.test', isEmailVerified: true },
    shipping: SHIPPING_ADDRESS,
  })

  assert.deepEqual(await readStock(world.db, art.id), { quantity: 1, status: 'for_sale' })
})

test('the last of a listing marks it sold', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { quantity: 1 })

  await placedOrder(context, buyer, [art.id])

  assert.deepEqual(await readStock(world.db, art.id), { quantity: 0, status: 'sold' })
})

test('it empties the cart', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)

  const order = await placedOrder(context, buyer, [art.id])
  const cart = await currentCart(context, buyer)

  assert.equal(order.customerId, buyer)
  assert.deepEqual(await readCartItems(world.db, cart.id), [])
})

test('it refuses an empty cart', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const buyer = await createCustomer(context)
  const cart = await currentCart(context, buyer)

  await assert.rejects(
    () =>
      placeOrder(context, {
        cartId: cart.id,
        purchaser: { id: buyer, email: 'ada@example.test', isEmailVerified: true },
        shipping: SHIPPING_ADDRESS,
      }),
    RangeError,
  )
})

async function readOrderItems(db: AppDatabase, orderId: number) {
  return db
    .selectFrom('orderItems')
    .select(['title', 'unitPriceCents', 'sellerId'])
    .where('orderId', '=', orderId)
    .execute()
}

async function readFulfillments(db: AppDatabase, orderId: number) {
  return db
    .selectFrom('fulfillments')
    .select(['sellerId', 'status', 'subtotalCents', 'feeCents', 'netCents'])
    .where('orderId', '=', orderId)
    .orderBy('sellerId')
    .execute()
}

async function readStock(db: AppDatabase, listingId: number) {
  return db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listingId)
    .executeTakeFirstOrThrow()
}

async function readCartItems(db: AppDatabase, cartId: number) {
  return db.selectFrom('cartItems').select('id').where('cartId', '=', cartId).execute()
}

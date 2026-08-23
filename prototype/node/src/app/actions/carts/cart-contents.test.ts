import { test } from 'node:test'
import assert from 'node:assert/strict'
import { addToCart } from './add-to-cart.ts'
import { cartContents } from './cart-contents.ts'
import { currentCart } from './current-cart.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('an empty cart totals nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const cart = await currentCart(context, customerId)

  const contents = await cartContents(context, cart.id)

  assert.deepEqual(contents.lines, [])
  assert.equal(contents.totals.itemCount, 0)
  assert.equal(contents.totals.subtotalCents, 0)
  assert.deepEqual(contents.totals.subtotalsBySeller, [])
})

test('it prices lines from the listings behind them', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { priceCents: 10_000, quantity: 3 })
  const cart = await currentCart(context, customerId)
  await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 3 })

  const contents = await cartContents(context, cart.id)

  assert.equal(contents.totals.itemCount, 3)
  assert.equal(contents.totals.subtotalCents, 30_000)
})

test('it splits the subtotal by seller, ascending by seller id', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const lowerSellerId = await createSeller(context)
  const higherSellerId = await createSeller(context)
  assert.ok(higherSellerId > lowerSellerId)

  const lowerListing = await createListing(context, lowerSellerId, { priceCents: 7_000 })
  const higherListing = await createListing(context, higherSellerId, { priceCents: 5_000 })
  const cart = await currentCart(context, customerId)
  // Added to the cart in reverse id order, so an ascending result proves the
  // sort and is not just an artifact of insertion order.
  await addToCart(context, { cartId: cart.id, listingId: higherListing.id, quantity: 1 })
  await addToCart(context, { cartId: cart.id, listingId: lowerListing.id, quantity: 1 })

  const contents = await cartContents(context, cart.id)

  assert.deepEqual(contents.totals.subtotalsBySeller, [
    { sellerId: lowerSellerId, subtotalCents: 7_000 },
    { sellerId: higherSellerId, subtotalCents: 5_000 },
  ])
})

test('it carries the listing details a page needs', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { title: 'Harbour at Dusk', quantity: 4 })
  const cart = await currentCart(context, customerId)
  await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 1 })

  const contents = await cartContents(context, cart.id)

  const [line] = contents.lines
  assert.equal(line?.title, 'Harbour at Dusk')
  assert.equal(line?.slug, art.slug)
  assert.equal(line?.status, 'for_sale')
  assert.equal(line?.availableQuantity, 4)
})

test('a line carries its own total, priced through cartLineTotal', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const customerId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { priceCents: 4_500, quantity: 3 })
  const cart = await currentCart(context, customerId)
  await addToCart(context, { cartId: cart.id, listingId: art.id, quantity: 3 })

  const contents = await cartContents(context, cart.id)

  assert.equal(contents.lines[0]?.lineTotalCents, 13_500)
})

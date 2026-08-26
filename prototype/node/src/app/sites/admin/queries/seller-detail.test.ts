import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../../test/fixture-ids.ts'
import { sellerDetail } from './seller-detail.ts'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import { removedListing, removeListing } from '../../../actions/moderation/remove-listing.ts'
import {
  createAdmin,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
} from '../../../test/commerce-world.ts'

test('an id that names no seller reads null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.equal(await sellerDetail(world.context, fixtureId('sel', 999)), null)
})

test('a seller with nothing yet reads empty lists and a zero balance', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')

  const detail = await sellerDetail(world.context, sellerId)

  assert.equal(detail?.seller.shopName, 'Blue Kiln Studio')
  assert.deepEqual(detail?.listings, [])
  assert.deepEqual(detail?.fulfillments, [])
  assert.deepEqual(detail?.payouts, [])
  assert.deepEqual(detail?.balance, { heldCents: 0, availableCents: 0, paidOutCents: 0 })
})

test('listings carry their active removal, fulfillments carry money, payouts appear once run', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const adminId = await createAdmin(world.context)

  const removedArtwork = await createListing(world.context, sellerId, { priceCents: 10_000 })
  const okListing = await createListing(world.context, sellerId, { priceCents: 45_000 })
  removedListing(
    await removeListing(world.context, {
      listingId: removedArtwork.id,
      adminId,
      kind: 'temporary',
      reason: 'Reported as counterfeit.',
    }),
  )

  const order = await paidOrder(world.context, customerId, [okListing.id])
  const fulfillment = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  await markShipped(world.context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123',
  })
  await confirmDelivered(world.context, fulfillment.id)
  await runWeeklyPayout(world.context, new Date('2026-08-24T12:00:00.000Z'))

  const detail = await sellerDetail(world.context, sellerId)
  assert.ok(detail)

  const removedRow = detail.listings.find((listing) => listing.id === removedArtwork.id)
  const okRow = detail.listings.find((listing) => listing.id === okListing.id)
  assert.ok(removedRow?.removal)
  assert.equal(removedRow.removal.reason, 'Reported as counterfeit.')
  assert.equal(okRow?.removal, null)

  assert.equal(detail.fulfillments.length, 1)
  const [fulfillmentRow] = detail.fulfillments
  assert.ok(fulfillmentRow)
  assert.equal(fulfillmentRow.subtotalCents, 45_000)
  assert.equal(fulfillmentRow.feeCents, 4_500)
  assert.equal(fulfillmentRow.netCents, 40_500)

  assert.equal(detail.payouts.length, 1)
  const [payout] = detail.payouts
  assert.ok(payout)
  assert.equal(payout.amountCents, 40_500)
  assert.deepEqual(detail.balance, { heldCents: 0, availableCents: 0, paidOutCents: 40_500 })
})

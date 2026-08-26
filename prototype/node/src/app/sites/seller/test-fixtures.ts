import { randomUUID } from 'node:crypto'
import { addToCart } from '../../actions/carts/add-to-cart.ts'
import { currentCart } from '../../actions/carts/current-cart.ts'
import { confirmDelivered, deliveredFulfillment } from '../../actions/fulfillments/confirm-delivered.ts'
import { markShipped, shippedFulfillment } from '../../actions/fulfillments/mark-shipped.ts'
import { changeListingStatus, changedListing } from '../../actions/listings/change-listing-status.ts'
import { createListing } from '../../actions/listings/create-listing.ts'
import { markNotificationRead } from '../../actions/notifications/mark-notification-read.ts'
import { notify } from '../../actions/notifications/notify.ts'
import { finalizeOrder } from '../../actions/orders/finalize-order.ts'
import { placeOrderOrThrow } from '../../actions/orders/place-order.ts'
import type { SellerId } from '../../core/ids/entity-ids.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import { cents } from '../../core/money.ts'
import type { NotificationMessage } from '../../core/notifications/notification-message.ts'
import type { Fulfillment, Listing, Notification } from '../../db/commerce-schema.ts'
import { signInAsCustomer, type TestApp } from '../../test/build-test-app.ts'
import { APPROVED_CARD, SHIPPING_ADDRESS } from '../../test/commerce-world.ts'

const DEFAULT_DRAFT: ListingDraft = {
  title: 'Harbour at Dusk',
  description: 'Oil on canvas.',
  medium: 'Oil',
  dimensions: '40 x 60 cm',
  priceCents: cents(45_000),
  quantity: 2,
}

export async function createTestListing(
  testApp: TestApp,
  sellerId: SellerId,
  overrides: Partial<ListingDraft> = {},
): Promise<Listing> {
  const { db, clock } = testApp

  return createListing({ db, clock }, { sellerId, draft: { ...DEFAULT_DRAFT, ...overrides } })
}

export async function createForSaleListing(
  testApp: TestApp,
  sellerId: SellerId,
  overrides: Partial<ListingDraft> = {},
): Promise<Listing> {
  const listing = await createTestListing(testApp, sellerId, overrides)
  const { db, clock } = testApp

  return changedListing(await changeListingStatus({ db, clock }, { listingId: listing.id, status: 'for_sale' }))
}

/**
 * A paid fulfillment: a freshly verified customer buys `listing` (or a new
 * for-sale one) with an approved card, which holds the seller's net in escrow
 * and tells them the item sold.
 */
export async function createFulfillment(
  testApp: TestApp,
  sellerId: SellerId,
  listing?: Listing,
): Promise<Fulfillment> {
  const { db, clock } = testApp
  const forSale = listing ?? (await createForSaleListing(testApp, sellerId))
  const email = `buyer-${forSale.id}-${randomUUID()}@example.com`
  const buyer = await signInAsCustomer(testApp, email)

  const cart = await currentCart({ db, clock }, buyer.id)
  await addToCart({ db, clock }, { cartId: cart.id, listingId: forSale.id, quantity: 1 })

  const order = await placeOrderOrThrow(
    { db, clock },
    {
      cartId: cart.id,
      purchaser: { id: buyer.id, email, isEmailVerified: true },
      shipping: SHIPPING_ADDRESS,
    },
  )
  await finalizeOrder({ db, clock }, { orderId: order.id, cardNumber: APPROVED_CARD })

  return db.selectFrom('fulfillments').selectAll().where('orderId', '=', order.id).executeTakeFirstOrThrow()
}

/** A fulfillment shipped and confirmed delivered, so its escrow is released. */
export async function createDeliveredFulfillment(
  testApp: TestApp,
  sellerId: SellerId,
  listing?: Listing,
): Promise<Fulfillment> {
  const { db, clock } = testApp
  const fulfillment = await createFulfillment(testApp, sellerId, listing)

  shippedFulfillment(
    await markShipped(
      { db, clock },
      { fulfillmentId: fulfillment.id, carrier: 'Royal Mail', trackingNumber: 'RM123456789GB' },
    ),
  )

  return deliveredFulfillment(await confirmDelivered({ db, clock }, fulfillment.id))
}

export async function createTestNotification(
  testApp: TestApp,
  sellerId: SellerId,
  overrides: Partial<NotificationMessage> & { read?: boolean } = {},
): Promise<Notification> {
  const { db, clock } = testApp
  const notification = await notify(
    { db, clock },
    {
      recipientType: 'seller',
      recipientId: sellerId,
      message: { subject: overrides.subject ?? 'Notice', body: overrides.body ?? 'Body', url: overrides.url ?? null },
    },
  )

  return overrides.read === true ? markNotificationRead({ db, clock }, notification.id) : notification
}

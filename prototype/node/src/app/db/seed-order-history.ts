import type { ActionContext } from '../actions/action-context.ts'
import { addToCart } from '../actions/carts/add-to-cart.ts'
import { confirmDelivered } from '../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../actions/escrow/run-weekly-payout.ts'
import { finalizeOrder } from '../actions/orders/finalize-order.ts'
import { placeOrderOrThrow } from '../actions/orders/place-order.ts'
import { fixedClock } from '../clock.ts'
import type { FulfillmentId, ListingId, OrderId } from '../core/ids/entity-ids.ts'
import type { Purchaser } from '../core/orders/purchaser.ts'
import { mustSucceed } from '../core/refusal.ts'
import type { ShippingAddress } from '../core/orders/shipping-address.ts'
import { newId } from '../ids.ts'
import type { Order, Payout } from './commerce-schema.ts'
import type { AppDatabase } from './database.ts'
import { requireListingId } from './seed-catalog.ts'
import type { SeededHermione } from './seed-customers.ts'
import { toTimestamp } from './timestamp.ts'

const APPROVED_CARD = '4242 4242 4242 4242'
const FIVE_MINUTES_MS = 5 * 60 * 1000
const PAYOUT_RUN_AT = new Date('2026-07-16T09:00:00.000Z')

const HERMIONE_SHIPPING: ShippingAddress = {
  name: 'Hermione Granger',
  line1: '12 Heathgate',
  line2: null,
  city: 'London',
  region: 'Hampstead',
  postalCode: 'NW11 7EB',
  country: 'GB',
}

export type SeededOrderHistory = {
  paidOrder: Order
  shippedOrder: Order
  shippedFulfillmentId: FulfillmentId
  deliveredOrder: Order
  payouts: readonly Payout[]
}

/**
 * Three single-item orders for Hermione, each against a different listing,
 * taken to a different point in the fulfillment lifecycle: one paid and
 * awaiting shipment, one shipped, one delivered. The weekly payout run then
 * settles the escrow the delivered order released, all through the same
 * actions the storefront and seller portal call.
 */
export async function seedOrderHistory(
  db: AppDatabase,
  hermione: SeededHermione,
  listingIdsByTitle: Record<string, ListingId>,
): Promise<SeededOrderHistory> {
  const purchaser: Purchaser = { id: hermione.id, email: hermione.email, isEmailVerified: true }

  const paidOrder = await placeAndPay(
    db,
    purchaser,
    requireListingId(listingIdsByTitle, 'Burrow Kitchen Tea Bowl'),
    new Date('2026-07-06T09:00:00.000Z'),
  )

  const shippedOrder = await placeAndPay(
    db,
    purchaser,
    requireListingId(listingIdsByTitle, 'Gryffindor Common Room, Late Morning'),
    new Date('2026-07-07T09:00:00.000Z'),
  )
  await ship(db, shippedOrder.id, 'Owl Post', 'OWL-2263-1187-GB', new Date('2026-07-08T09:00:00.000Z'))
  const shippedFulfillmentId = await fulfillmentIdFor(db, shippedOrder.id)

  const deliveredOrder = await placeAndPay(
    db,
    purchaser,
    requireListingId(listingIdsByTitle, 'Garden Gnome in Reclaimed Oak'),
    new Date('2026-07-06T11:00:00.000Z'),
  )
  await ship(db, deliveredOrder.id, 'Knight Bus Parcel', 'KB-9400-1189-2231', new Date('2026-07-08T10:00:00.000Z'))
  await deliver(db, deliveredOrder.id, new Date('2026-07-10T14:00:00.000Z'))

  const payouts = await runWeeklyPayout({ db, clock: fixedClock(PAYOUT_RUN_AT) }, PAYOUT_RUN_AT)

  return { paidOrder, shippedOrder, shippedFulfillmentId, deliveredOrder, payouts }
}

/** A cart of its own, the way a shopper's checkout does — never Hermione's
 * standing cart, which stays hers to keep shopping in. */
async function placeAndPay(
  db: AppDatabase,
  purchaser: Purchaser,
  listingId: ListingId,
  placedAt: Date,
): Promise<Order> {
  const cart = await db
    .insertInto('carts')
    .values({
      id: newId('crt', placedAt),
      customerId: purchaser.id,
      createdAt: toTimestamp(placedAt),
    })
    .returningAll()
    .executeTakeFirstOrThrow()

  const placingContext: ActionContext = { db, clock: fixedClock(placedAt) }
  await addToCart(placingContext, { cartId: cart.id, listingId, quantity: 1 })
  const order = await placeOrderOrThrow(placingContext, { cartId: cart.id, purchaser, shipping: HERMIONE_SHIPPING })

  const finalizingContext: ActionContext = { db, clock: fixedClock(new Date(placedAt.getTime() + FIVE_MINUTES_MS)) }
  return finalizeOrder(finalizingContext, { orderId: order.id, cardNumber: APPROVED_CARD })
}

async function fulfillmentIdFor(db: AppDatabase, orderId: OrderId): Promise<FulfillmentId> {
  const fulfillment = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .executeTakeFirstOrThrow()

  return fulfillment.id
}

async function ship(
  db: AppDatabase,
  orderId: OrderId,
  carrier: string,
  trackingNumber: string,
  shippedAt: Date,
): Promise<void> {
  mustSucceed(
    await markShipped(
      { db, clock: fixedClock(shippedAt) },
      { fulfillmentId: await fulfillmentIdFor(db, orderId), carrier, trackingNumber },
    ),
  )
}

async function deliver(db: AppDatabase, orderId: OrderId, deliveredAt: Date): Promise<void> {
  mustSucceed(
    await confirmDelivered({ db, clock: fixedClock(deliveredAt) }, await fulfillmentIdFor(db, orderId)),
  )
}

import type { ActionContext } from '../actions/action-context.ts'
import { addToCart } from '../actions/carts/add-to-cart.ts'
import { confirmDelivered } from '../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../actions/escrow/run-weekly-payout.ts'
import { finalizeOrder } from '../actions/orders/finalize-order.ts'
import { placeOrder } from '../actions/orders/place-order.ts'
import { fixedClock } from '../clock.ts'
import type { Purchaser } from '../core/orders/purchaser.ts'
import type { ShippingAddress } from '../core/orders/shipping-address.ts'
import type { Order, Payout } from './commerce-schema.ts'
import type { AppDatabase } from './database.ts'
import type { SeededCasey } from './seed-customers.ts'
import { toTimestamp } from './timestamp.ts'

const APPROVED_CARD = '4242 4242 4242 4242'
const FIVE_MINUTES_MS = 5 * 60 * 1000
const PAYOUT_RUN_AT = new Date('2026-07-16T09:00:00.000Z')

const CASEY_SHIPPING: ShippingAddress = {
  name: 'Casey Whitfield',
  line1: '48 Harbor Street',
  line2: null,
  city: 'Portland',
  region: 'Oregon',
  postalCode: '97201',
  country: 'US',
}

export type SeededOrderHistory = {
  paidOrder: Order
  shippedOrder: Order
  deliveredOrder: Order
  payouts: readonly Payout[]
}

/**
 * Three single-item orders for Casey, each against a different listing, taken
 * to a different point in the fulfillment lifecycle: one paid and awaiting
 * shipment, one shipped, one delivered. The weekly payout run then settles the
 * escrow the delivered order released, all through the same actions the
 * storefront and seller portal call.
 */
export async function seedOrderHistory(
  db: AppDatabase,
  casey: SeededCasey,
  listingIdsByTitle: Record<string, number>,
): Promise<SeededOrderHistory> {
  const purchaser: Purchaser = { id: casey.id, email: casey.email, isEmailVerified: true }

  const paidOrder = await placeAndPay(
    db,
    purchaser,
    listingIdsByTitle['Ash-Glazed Tea Bowl'] ?? 0,
    new Date('2026-07-06T09:00:00.000Z'),
  )

  const shippedOrder = await placeAndPay(
    db,
    purchaser,
    listingIdsByTitle['Kitchen Table, Late Morning'] ?? 0,
    new Date('2026-07-07T09:00:00.000Z'),
  )
  await ship(db, shippedOrder.id, 'UPS', '1Z999AA10123456784', new Date('2026-07-08T09:00:00.000Z'))

  const deliveredOrder = await placeAndPay(
    db,
    purchaser,
    listingIdsByTitle['Standing Figure in Reclaimed Oak'] ?? 0,
    new Date('2026-07-06T11:00:00.000Z'),
  )
  await ship(db, deliveredOrder.id, 'USPS', '9400111899223197428490', new Date('2026-07-08T10:00:00.000Z'))
  await deliver(db, deliveredOrder.id, new Date('2026-07-10T14:00:00.000Z'))

  const payouts = await runWeeklyPayout({ db, clock: fixedClock(PAYOUT_RUN_AT) }, PAYOUT_RUN_AT)

  return { paidOrder, shippedOrder, deliveredOrder, payouts }
}

/** A cart of its own, the way a shopper's checkout does — never Casey's
 * standing cart, which stays hers to keep shopping in. */
async function placeAndPay(
  db: AppDatabase,
  purchaser: Purchaser,
  listingId: number,
  placedAt: Date,
): Promise<Order> {
  const cart = await db
    .insertInto('carts')
    .values({ customerId: purchaser.id, createdAt: toTimestamp(placedAt) })
    .returningAll()
    .executeTakeFirstOrThrow()

  const placingContext: ActionContext = { db, clock: fixedClock(placedAt) }
  await addToCart(placingContext, { cartId: cart.id, listingId, quantity: 1 })
  const order = await placeOrder(placingContext, { cartId: cart.id, purchaser, shipping: CASEY_SHIPPING })

  const finalizingContext: ActionContext = { db, clock: fixedClock(new Date(placedAt.getTime() + FIVE_MINUTES_MS)) }
  return finalizeOrder(finalizingContext, { orderId: order.id, cardNumber: APPROVED_CARD })
}

async function fulfillmentIdFor(db: AppDatabase, orderId: number): Promise<number> {
  const fulfillment = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .executeTakeFirstOrThrow()

  return fulfillment.id
}

async function ship(
  db: AppDatabase,
  orderId: number,
  carrier: string,
  trackingNumber: string,
  shippedAt: Date,
): Promise<void> {
  await markShipped(
    { db, clock: fixedClock(shippedAt) },
    { fulfillmentId: await fulfillmentIdFor(db, orderId), carrier, trackingNumber },
  )
}

async function deliver(db: AppDatabase, orderId: number, deliveredAt: Date): Promise<void> {
  await confirmDelivered({ db, clock: fixedClock(deliveredAt) }, await fulfillmentIdFor(db, orderId))
}

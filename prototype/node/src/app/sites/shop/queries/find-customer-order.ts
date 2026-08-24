import type { CustomerId, FulfillmentId, OrderId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { Order, OrderItem, Payment } from '../../../db/commerce-schema.ts'
import type { Cents } from '../../../core/money.ts'
import {
  canTransitionFulfillment,
  isReversed,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'

/** What the customer is told when a half of their order was reversed. */
export type OrderFulfillmentRefund = { amountCents: Cents; reason: string }

/** One seller's half of an order, as the customer follows it. */
export type OrderFulfillment = {
  id: FulfillmentId
  status: FulfillmentStatus
  carrier: string | null
  trackingNumber: string | null
  seller: { shopName: string | null; email: string }
  items: readonly OrderItem[]
  canConfirmDelivery: boolean
  /** Set exactly while the fulfillment is declined or refunded. */
  refund: OrderFulfillmentRefund | null
}

export type CustomerOrder = {
  order: Order
  fulfillments: readonly OrderFulfillment[]
  /** The most recent charge attempt, which is what carries a decline reason. */
  lastPayment: Payment | null
}

/**
 * An order read as its own customer. Someone else's order comes back null, so
 * the page that asked answers "not found" and says nothing about whether it
 * exists.
 */
export async function findCustomerOrder(
  db: AppDatabase,
  input: { orderId: OrderId; customerId: CustomerId },
): Promise<CustomerOrder | null> {
  const order = await db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', input.orderId)
    .where('customerId', '=', input.customerId)
    .executeTakeFirst()

  if (order === undefined) return null

  const items = await db
    .selectFrom('orderItems')
    .selectAll()
    .where('orderId', '=', order.id)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  const rows = await db
    .selectFrom('fulfillments')
    .innerJoin('sellers', 'sellers.id', 'fulfillments.sellerId')
    .select([
      'fulfillments.id as id',
      'fulfillments.sellerId as sellerId',
      'fulfillments.status as status',
      'fulfillments.carrier as carrier',
      'fulfillments.trackingNumber as trackingNumber',
      'sellers.shopName as sellerShopName',
      'sellers.email as sellerEmail',
    ])
    .where('fulfillments.orderId', '=', order.id)
    .orderBy('fulfillments.createdAt')
    .orderBy('fulfillments.id')
    .execute()

  const refunds = await db
    .selectFrom('refunds')
    .select(['fulfillmentId', 'amountCents', 'reason'])
    .where('orderId', '=', order.id)
    .execute()

  const lastPayment = await db
    .selectFrom('payments')
    .selectAll()
    .where('orderId', '=', order.id)
    .orderBy('processedAt', 'desc')
    .orderBy('id', 'desc')
    .executeTakeFirst()

  return {
    order,
    lastPayment: lastPayment ?? null,
    fulfillments: rows.map((row) => ({
      id: row.id,
      status: row.status,
      carrier: row.carrier,
      trackingNumber: row.trackingNumber,
      seller: { shopName: row.sellerShopName, email: row.sellerEmail },
      items: items.filter((item) => item.sellerId === row.sellerId),
      canConfirmDelivery: canTransitionFulfillment(row.status, 'delivered'),
      refund: isReversed(row.status)
        ? (refunds.find((refund) => refund.fulfillmentId === row.id) ?? null)
        : null,
    })),
  }
}

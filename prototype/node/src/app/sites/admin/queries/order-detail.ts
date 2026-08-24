import type { ActionContext } from '../../../actions/action-context.ts'
import { cartLineTotal } from '../../../core/cart/cart-line.ts'
import type { CustomerId, FulfillmentId, OrderId, SellerId } from '../../../core/ids/entity-ids.ts'
import { customerName } from '../../../core/messaging/participant-name.ts'
import type { Cents } from '../../../core/money.ts'
import {
  canTransitionFulfillment,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'
import { shopName } from '../../../core/shop/shop-name.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { Order, OrderItem, Payment, Refund } from '../../../db/commerce-schema.ts'

/** One seller's half of the order, as the admin sees every seller's. */
export type OrderDetailFulfillment = {
  id: FulfillmentId
  sellerId: SellerId
  sellerName: string
  status: FulfillmentStatus
  subtotalCents: Cents
  feeCents: Cents
  netCents: Cents
  /** Whether the refund form is offered against this half of the order. */
  canRefund: boolean
}

/** An order item priced for display: what the line comes to at its quantity. */
export type OrderDetailItem = OrderItem & { lineTotalCents: Cents }

export type OrderDetail = {
  order: Order
  customer: { id: CustomerId; name: string }
  items: readonly OrderDetailItem[]
  payments: readonly Payment[]
  fulfillments: readonly OrderDetailFulfillment[]
  refunds: readonly Refund[]
}

/**
 * The order page's whole read: null when the id names nobody. Unlike
 * `findCustomerOrder`, this is not scoped to a customer — an admin reads any
 * order.
 */
export async function orderDetail(
  context: Pick<ActionContext, 'db'>,
  orderId: OrderId,
): Promise<OrderDetail | null> {
  const { db } = context
  const order = await db.selectFrom('orders').selectAll().where('id', '=', orderId).executeTakeFirst()
  if (order === undefined) return null

  const customer = await db
    .selectFrom('customers')
    .select(['id', 'name', 'email'])
    .where('id', '=', order.customerId)
    .executeTakeFirstOrThrow()

  const items = await itemsForOrder(db, order.id)
  const payments = await paymentsForOrder(db, order.id)
  const fulfillments = await fulfillmentsForOrder(db, order.id)
  const refunds = await refundsForOrder(db, order.id)

  return {
    order,
    customer: { id: customer.id, name: customerName(customer) },
    items,
    payments,
    fulfillments,
    refunds,
  }
}

async function itemsForOrder(db: AppDatabase, orderId: OrderId): Promise<readonly OrderDetailItem[]> {
  const items = await db
    .selectFrom('orderItems')
    .selectAll()
    .where('orderId', '=', orderId)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  return items.map((item) => ({ ...item, lineTotalCents: cartLineTotal(item) }))
}

async function paymentsForOrder(db: AppDatabase, orderId: OrderId): Promise<readonly Payment[]> {
  return db
    .selectFrom('payments')
    .selectAll()
    .where('orderId', '=', orderId)
    .orderBy('processedAt', 'desc')
    .orderBy('id', 'desc')
    .execute()
}

async function fulfillmentsForOrder(db: AppDatabase, orderId: OrderId): Promise<readonly OrderDetailFulfillment[]> {
  const rows = await db
    .selectFrom('fulfillments')
    .innerJoin('sellers', 'sellers.id', 'fulfillments.sellerId')
    .select([
      'fulfillments.id as id',
      'fulfillments.sellerId as sellerId',
      'fulfillments.status as status',
      'fulfillments.subtotalCents as subtotalCents',
      'fulfillments.feeCents as feeCents',
      'fulfillments.netCents as netCents',
      'sellers.shopName as sellerShopName',
      'sellers.email as sellerEmail',
    ])
    .where('fulfillments.orderId', '=', orderId)
    .orderBy('fulfillments.createdAt')
    .orderBy('fulfillments.id')
    .execute()

  return rows.map((row) => ({
    id: row.id,
    sellerId: row.sellerId,
    sellerName: shopName({ shopName: row.sellerShopName, email: row.sellerEmail }),
    status: row.status,
    subtotalCents: row.subtotalCents,
    feeCents: row.feeCents,
    netCents: row.netCents,
    canRefund: canTransitionFulfillment(row.status, 'refunded'),
  }))
}

async function refundsForOrder(db: AppDatabase, orderId: OrderId): Promise<readonly Refund[]> {
  return db
    .selectFrom('refunds')
    .selectAll()
    .where('orderId', '=', orderId)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()
}

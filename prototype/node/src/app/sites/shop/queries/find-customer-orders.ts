import type { AppDatabase } from '../../../db/database.ts'
import type { OrderStatus } from '../../../core/orders/order-status.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

/** One row of the orders list: what it cost, where it is, and what is in it. */
export type OrderSummary = {
  id: number
  status: OrderStatus
  totalCents: number
  placedAt: Timestamp
  titles: readonly string[]
}

export async function findCustomerOrders(
  db: AppDatabase,
  customerId: number,
): Promise<readonly OrderSummary[]> {
  const orders = await db
    .selectFrom('orders')
    .select(['id', 'status', 'totalCents', 'placedAt'])
    .where('customerId', '=', customerId)
    .orderBy('id', 'desc')
    .execute()

  if (orders.length === 0) return []

  const items = await db
    .selectFrom('orderItems')
    .select(['orderId', 'title'])
    .where(
      'orderId',
      'in',
      orders.map((order) => order.id),
    )
    .orderBy('id')
    .execute()

  return orders.map((order) => ({
    ...order,
    titles: items.filter((item) => item.orderId === order.id).map((item) => item.title),
  }))
}

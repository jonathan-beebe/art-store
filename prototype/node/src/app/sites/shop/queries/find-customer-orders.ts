import type { CustomerId, OrderId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { OrderStatus } from '../../../core/orders/order-status.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

/** One row of the orders list: what it cost, where it is, and what is in it. */
export type OrderSummary = {
  id: OrderId
  status: OrderStatus
  totalCents: number
  placedAt: Timestamp
  titles: readonly string[]
}

export async function findCustomerOrders(
  db: AppDatabase,
  customerId: CustomerId,
): Promise<readonly OrderSummary[]> {
  const orders = await db
    .selectFrom('orders')
    .select(['id', 'status', 'totalCents', 'placedAt'])
    .where('customerId', '=', customerId)
    .orderBy('placedAt', 'desc')
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
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  const titlesByOrder = new Map<OrderId, string[]>()
  for (const item of items) {
    const titles = titlesByOrder.get(item.orderId)
    if (titles === undefined) {
      titlesByOrder.set(item.orderId, [item.title])
    } else {
      titles.push(item.title)
    }
  }

  return orders.map((order) => ({
    ...order,
    titles: titlesByOrder.get(order.id) ?? [],
  }))
}

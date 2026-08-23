import type { ActionContext } from '../../../actions/action-context.ts'
import type { Cents } from '../../../core/money.ts'
import type { FulfillmentStatus } from '../../../core/orders/fulfillment-status.ts'
import type { OrderStatus } from '../../../core/orders/order-status.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export type OrderRowFilters = {
  status?: OrderStatus
  customerId?: number
}

export type OrderRow = {
  id: number
  customerEmail: string | null
  status: OrderStatus
  itemCount: number
  subtotalCents: Cents
  totalCents: Cents
  placedAt: Timestamp
  fulfillmentStatuses: FulfillmentStatus[]
}

/**
 * Every order, each rolled up with the item count and fulfillment statuses
 * behind it. Reads `order_items` and `fulfillments` once for the whole page
 * rather than per order, so a large table stays a handful of round trips.
 */
export async function orderRows(
  context: Pick<ActionContext, 'db'>,
  filters: OrderRowFilters = {},
): Promise<OrderRow[]> {
  const { db } = context
  let query = db.selectFrom('orders').selectAll().orderBy('id', 'desc')

  if (filters.status !== undefined) query = query.where('status', '=', filters.status)
  if (filters.customerId !== undefined) query = query.where('customerId', '=', filters.customerId)

  const orders = await query.execute()
  const orderIds = orders.map((order) => order.id)
  const itemCounts = await itemCountsByOrder(db, orderIds)
  const fulfillmentStatuses = await fulfillmentStatusesByOrder(db, orderIds)

  return orders.map((order) => ({
    id: order.id,
    customerEmail: order.email,
    status: order.status,
    itemCount: itemCounts.get(order.id) ?? 0,
    subtotalCents: order.subtotalCents,
    totalCents: order.totalCents,
    placedAt: order.placedAt,
    fulfillmentStatuses: fulfillmentStatuses.get(order.id) ?? [],
  }))
}

async function itemCountsByOrder(
  db: ActionContext['db'],
  orderIds: readonly number[],
): Promise<ReadonlyMap<number, number>> {
  const byOrder = new Map<number, number>()
  if (orderIds.length === 0) return byOrder

  const rows = await db
    .selectFrom('orderItems')
    .select(['orderId', 'quantity'])
    .where('orderId', 'in', orderIds)
    .execute()

  for (const row of rows) {
    byOrder.set(row.orderId, (byOrder.get(row.orderId) ?? 0) + row.quantity)
  }

  return byOrder
}

async function fulfillmentStatusesByOrder(
  db: ActionContext['db'],
  orderIds: readonly number[],
): Promise<ReadonlyMap<number, FulfillmentStatus[]>> {
  const byOrder = new Map<number, FulfillmentStatus[]>()
  if (orderIds.length === 0) return byOrder

  const rows = await db
    .selectFrom('fulfillments')
    .select(['orderId', 'status'])
    .where('orderId', 'in', orderIds)
    .execute()

  for (const row of rows) {
    byOrder.set(row.orderId, [...(byOrder.get(row.orderId) ?? []), row.status])
  }

  return byOrder
}

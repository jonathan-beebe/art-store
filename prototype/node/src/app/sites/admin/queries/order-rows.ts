import type { Expression, ExpressionBuilder, SqlBool } from 'kysely'
import type { ActionContext } from '../../../actions/action-context.ts'
import type { CustomerId, OrderId } from '../../../core/ids/entity-ids.ts'
import type { Cents } from '../../../core/money.ts'
import type { FulfillmentStatus } from '../../../core/orders/fulfillment-status.ts'
import type { OrderStatus } from '../../../core/orders/order-status.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'
import { toCount } from '../../../db/count.ts'
import type { Database } from '../../../db/schema.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export type OrderRowFilters = {
  status?: OrderStatus
  customerId?: CustomerId
}

export type OrderRow = {
  id: OrderId
  customerEmail: string | null
  status: OrderStatus
  itemCount: number
  subtotalCents: Cents
  totalCents: Cents
  placedAt: Timestamp
  fulfillmentStatuses: FulfillmentStatus[]
}

type OrdersFilter = ExpressionBuilder<Database, 'orders'>

function matchesOrderRowFilters(eb: OrdersFilter, filters: OrderRowFilters): Expression<SqlBool>[] {
  const conditions: Expression<SqlBool>[] = []

  if (filters.status !== undefined) conditions.push(eb('orders.status', '=', filters.status))
  if (filters.customerId !== undefined) conditions.push(eb('orders.customerId', '=', filters.customerId))

  return conditions
}

/** How many orders match `filters`, independent of which page of them is shown. */
export async function countOrderRows(
  context: Pick<ActionContext, 'db'>,
  filters: OrderRowFilters = {},
): Promise<number> {
  const counted = await context.db
    .selectFrom('orders')
    .select(({ fn }) => fn.countAll<string | number | bigint>().as('total'))
    .where((eb) => eb.and(matchesOrderRowFilters(eb, filters)))
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}

/**
 * One page of orders, each rolled up with the item count and fulfillment
 * statuses behind it. Reads `order_items` and `fulfillments` once for the
 * page rather than per order, so a large table stays a handful of round trips.
 */
export async function orderRows(
  context: Pick<ActionContext, 'db'>,
  filters: OrderRowFilters = {},
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<OrderRow[]> {
  const { db } = context
  const orders = await db
    .selectFrom('orders')
    .select(['id', 'email', 'status', 'subtotalCents', 'totalCents', 'placedAt'])
    .where((eb) => eb.and(matchesOrderRowFilters(eb, filters)))
    .orderBy('placedAt', 'desc')
    .orderBy('id', 'desc')
    .offset(page.offset)
    .limit(page.limit)
    .execute()

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
  orderIds: readonly OrderId[],
): Promise<ReadonlyMap<OrderId, number>> {
  const byOrder = new Map<OrderId, number>()
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
  orderIds: readonly OrderId[],
): Promise<ReadonlyMap<OrderId, FulfillmentStatus[]>> {
  const byOrder = new Map<OrderId, FulfillmentStatus[]>()
  if (orderIds.length === 0) return byOrder

  const rows = await db
    .selectFrom('fulfillments')
    .select(['orderId', 'status'])
    .where('orderId', 'in', orderIds)
    .execute()

  for (const row of rows) {
    const statuses = byOrder.get(row.orderId)
    if (statuses === undefined) {
      byOrder.set(row.orderId, [row.status])
    } else {
      statuses.push(row.status)
    }
  }

  return byOrder
}

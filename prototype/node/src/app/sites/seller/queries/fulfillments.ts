import type { FulfillmentId, OrderId, SellerId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { Fulfillment, Order, OrderItem } from '../../../db/commerce-schema.ts'

export type FulfillmentWithOrder = Fulfillment & { shippingName: string; placedAt: Order['placedAt'] }

export async function fulfillmentsForSeller(
  db: AppDatabase,
  sellerId: SellerId,
): Promise<readonly FulfillmentWithOrder[]> {
  return db
    .selectFrom('fulfillments')
    .innerJoin('orders', 'orders.id', 'fulfillments.orderId')
    .selectAll('fulfillments')
    .select(['orders.shippingName as shippingName', 'orders.placedAt as placedAt'])
    .where('fulfillments.sellerId', '=', sellerId)
    .orderBy('fulfillments.createdAt', 'desc')
    .orderBy('fulfillments.id', 'desc')
    .execute()
}

export async function awaitingShipmentCount(db: AppDatabase, sellerId: SellerId): Promise<number> {
  const row = await db
    .selectFrom('fulfillments')
    .select((eb) => eb.fn.countAll().as('count'))
    .where('sellerId', '=', sellerId)
    .where('status', '=', 'awaiting_shipment')
    .executeTakeFirstOrThrow()

  return Number(row.count)
}

export async function ownedFulfillment(
  db: AppDatabase,
  sellerId: SellerId,
  fulfillmentId: FulfillmentId,
): Promise<{ fulfillment: Fulfillment; order: Order } | null> {
  const fulfillment = await db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillmentId)
    .where('sellerId', '=', sellerId)
    .executeTakeFirst()
  if (fulfillment === undefined) return null

  const order = await db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', fulfillment.orderId)
    .executeTakeFirstOrThrow()

  return { fulfillment, order }
}

/** The lines of an order that belong to this seller — a seller's "order" is
 * one fulfillment, and only these items ship under it. */
export async function orderItemsForSeller(
  db: AppDatabase,
  orderId: OrderId,
  sellerId: SellerId,
): Promise<readonly OrderItem[]> {
  return db
    .selectFrom('orderItems')
    .selectAll()
    .where('orderId', '=', orderId)
    .where('sellerId', '=', sellerId)
    .execute()
}

/** Item titles for many fulfillments in one read, keyed by the fulfillment's
 * order id — what the earnings and orders tables show in the "items" column. */
export async function itemTitlesByOrder(
  db: AppDatabase,
  orderIds: readonly OrderId[],
  sellerId: SellerId,
): Promise<ReadonlyMap<OrderId, readonly string[]>> {
  const byOrder = new Map<OrderId, string[]>()
  if (orderIds.length === 0) return byOrder

  const rows = await db
    .selectFrom('orderItems')
    .select(['orderId', 'title'])
    .where('orderId', 'in', orderIds)
    .where('sellerId', '=', sellerId)
    .execute()

  for (const row of rows) {
    byOrder.set(row.orderId, [...(byOrder.get(row.orderId) ?? []), row.title])
  }

  return byOrder
}

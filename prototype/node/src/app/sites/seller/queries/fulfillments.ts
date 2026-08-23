import type { AppDatabase } from '../../../db/database.ts'
import type { Fulfillment, Order, OrderItem } from '../../../db/commerce-schema.ts'

export type FulfillmentWithOrder = Fulfillment & { shippingName: string; placedAt: Order['placedAt'] }

export async function fulfillmentsForSeller(
  db: AppDatabase,
  sellerId: number,
): Promise<readonly FulfillmentWithOrder[]> {
  return db
    .selectFrom('fulfillments')
    .innerJoin('orders', 'orders.id', 'fulfillments.orderId')
    .selectAll('fulfillments')
    .select(['orders.shippingName as shippingName', 'orders.placedAt as placedAt'])
    .where('fulfillments.sellerId', '=', sellerId)
    .orderBy('fulfillments.id', 'desc')
    .execute()
}

export async function awaitingShipmentCount(db: AppDatabase, sellerId: number): Promise<number> {
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
  sellerId: number,
  fulfillmentId: number,
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
  orderId: number,
  sellerId: number,
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
  orderIds: readonly number[],
  sellerId: number,
): Promise<ReadonlyMap<number, readonly string[]>> {
  const byOrder = new Map<number, string[]>()
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

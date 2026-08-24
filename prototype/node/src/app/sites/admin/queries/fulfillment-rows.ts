import type { ActionContext } from '../../../actions/action-context.ts'
import type { FulfillmentId, OrderId, SellerId } from '../../../core/ids/entity-ids.ts'
import type { Cents } from '../../../core/money.ts'
import type { FulfillmentStatus } from '../../../core/orders/fulfillment-status.ts'
import { shopName } from '../../../core/shop/shop-name.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export type FulfillmentRowFilters = {
  status?: FulfillmentStatus
  sellerId?: SellerId
}

export type FulfillmentRow = {
  id: FulfillmentId
  orderId: OrderId
  sellerId: SellerId
  sellerName: string
  status: FulfillmentStatus
  subtotalCents: Cents
  feeCents: Cents
  netCents: Cents
  shippedAt: Timestamp | null
  deliveredAt: Timestamp | null
}

/** Every fulfillment (one row per order × seller), with the seller it ships under. */
export async function fulfillmentRows(
  context: Pick<ActionContext, 'db'>,
  filters: FulfillmentRowFilters = {},
): Promise<FulfillmentRow[]> {
  const { db } = context
  let query = db
    .selectFrom('fulfillments')
    .innerJoin('sellers', 'sellers.id', 'fulfillments.sellerId')
    .selectAll('fulfillments')
    .select(['sellers.shopName as sellerShopName', 'sellers.email as sellerEmail'])
    .orderBy('fulfillments.createdAt', 'desc')
    .orderBy('fulfillments.id', 'desc')

  if (filters.status !== undefined) query = query.where('fulfillments.status', '=', filters.status)
  if (filters.sellerId !== undefined) query = query.where('fulfillments.sellerId', '=', filters.sellerId)

  const rows = await query.execute()

  return rows.map((row) => ({
    id: row.id,
    orderId: row.orderId,
    sellerId: row.sellerId,
    sellerName: shopName({ shopName: row.sellerShopName, email: row.sellerEmail }),
    status: row.status,
    subtotalCents: row.subtotalCents,
    feeCents: row.feeCents,
    netCents: row.netCents,
    shippedAt: row.shippedAt,
    deliveredAt: row.deliveredAt,
  }))
}

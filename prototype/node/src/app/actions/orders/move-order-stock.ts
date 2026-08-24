import type { OrderId, SellerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { stockAfter } from '../../core/listings/listing-stock.ts'
import type { StockChange } from '../../core/listings/stock-change.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** Which of an order's lines the change reaches; left out, all of them. */
export type OrderStockScope = { sellerId?: SellerId }

/**
 * Applies one stock change to every listing an order holds. Placement claims
 * the stock, a declined card or a cancellation hands it back, and a retry
 * claims it again — which is why `sold -> for_sale` is a legal move. A decline
 * hands back one seller's lines only, which is what `sellerId` narrows to.
 */
export async function moveOrderStock(
  context: ActionContext,
  orderId: OrderId,
  change: StockChange,
  scope: OrderStockScope = {},
): Promise<void> {
  const { db, clock } = context
  let query = db
    .selectFrom('orderItems')
    .innerJoin('listings', 'listings.id', 'orderItems.listingId')
    .select([
      'listings.id as listingId',
      'listings.quantity as availableQuantity',
      'listings.status as status',
      'orderItems.quantity as quantity',
    ])
    .where('orderItems.orderId', '=', orderId)
    .orderBy('orderItems.createdAt')
    .orderBy('orderItems.id')

  if (scope.sellerId !== undefined) {
    query = query.where('orderItems.sellerId', '=', scope.sellerId)
  }

  const items = await query.execute()

  const updatedAt = toTimestamp(clock.now())

  for (const item of items) {
    const stock = stockAfter(change, {
      quantity: item.availableQuantity,
      status: item.status,
      items: item.quantity,
    })

    await db
      .updateTable('listings')
      .set({ quantity: stock.quantity, status: stock.status, updatedAt })
      .where('id', '=', item.listingId)
      .execute()
  }
}

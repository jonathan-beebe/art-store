import type { ActionContext } from '../../../actions/action-context.ts'
import { cartLineTotal } from '../../../core/cart/cart-line.ts'
import type { FulfillmentId, SellerId } from '../../../core/ids/entity-ids.ts'
import type { Cents } from '../../../core/money.ts'
import { canRefundFulfillment } from '../../../core/orders/refund.ts'
import { shopName } from '../../../core/shop/shop-name.ts'
import type { Fulfillment, LedgerEntry, Order, OrderItem, Refund } from '../../../db/commerce-schema.ts'

/** An order item priced for display: what the line comes to at its quantity. */
export type FulfillmentDetailItem = OrderItem & { lineTotalCents: Cents }

export type FulfillmentDetail = {
  fulfillment: Fulfillment
  order: Order
  seller: { id: SellerId; name: string; email: string }
  items: readonly FulfillmentDetailItem[]
  ledgerEntries: readonly LedgerEntry[]
  /** The reversal against this fulfillment, or null while it has not been reversed. */
  refund: Refund | null
  /** Whether the refund form is offered against this fulfillment. */
  canRefund: boolean
}

/**
 * The fulfillment page's whole read: null when the id names nobody. Unlike
 * `ownedFulfillment`, this is not scoped to a seller — an admin reads any
 * fulfillment.
 */
export async function fulfillmentDetail(
  context: Pick<ActionContext, 'db'>,
  fulfillmentId: FulfillmentId,
): Promise<FulfillmentDetail | null> {
  const { db } = context
  const fulfillment = await db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillmentId)
    .executeTakeFirst()
  if (fulfillment === undefined) return null

  const order = await db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', fulfillment.orderId)
    .executeTakeFirstOrThrow()

  const sellerRow = await db
    .selectFrom('sellers')
    .select(['id', 'shopName', 'email'])
    .where('id', '=', fulfillment.sellerId)
    .executeTakeFirstOrThrow()

  const orderItems = await db
    .selectFrom('orderItems')
    .selectAll()
    .where('orderId', '=', fulfillment.orderId)
    .where('sellerId', '=', fulfillment.sellerId)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()
  const items = orderItems.map((item) => ({ ...item, lineTotalCents: cartLineTotal(item) }))

  const ledgerEntries = await db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('fulfillmentId', '=', fulfillment.id)
    .orderBy('occurredAt')
    .orderBy('id')
    .execute()

  const refund = await db
    .selectFrom('refunds')
    .selectAll()
    .where('fulfillmentId', '=', fulfillment.id)
    .executeTakeFirst()

  const approvedPayment = await db
    .selectFrom('payments')
    .select('id')
    .where('orderId', '=', fulfillment.orderId)
    .where('status', '=', 'approved')
    .orderBy('processedAt', 'desc')
    .orderBy('id', 'desc')
    .executeTakeFirst()

  return {
    fulfillment,
    order,
    seller: { id: sellerRow.id, name: shopName(sellerRow), email: sellerRow.email },
    items,
    ledgerEntries,
    refund: refund ?? null,
    canRefund: canRefundFulfillment({ status: fulfillment.status, paymentId: approvedPayment?.id ?? null }),
  }
}

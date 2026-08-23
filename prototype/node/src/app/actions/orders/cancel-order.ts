import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { moveOrderStock } from './move-order-stock.ts'
import { stockChangeBetween } from '../../core/orders/order-stock.ts'
import { transitionOrder } from '../../core/orders/order-status.ts'
import type { Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/**
 * Cancels an order that has not been paid, handing back the stock placement
 * took. A paid order has no route here — the transition table refuses it.
 */
export async function cancelOrder(context: ActionContext, orderId: number): Promise<Order> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const order = await db
      .selectFrom('orders')
      .selectAll()
      .where('id', '=', orderId)
      .executeTakeFirstOrThrow()

    const status = transitionOrder(order.status, 'cancelled')

    await moveOrderStock(transacted, order.id, stockChangeBetween({ from: order.status, to: status }))

    return db
      .updateTable('orders')
      .set({ status, cancelledAt: toTimestamp(clock.now()) })
      .where('id', '=', order.id)
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

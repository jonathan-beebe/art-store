import type { OrderId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { moveOrderStock } from './move-order-stock.ts'
import { stockChangeBetween } from '../../core/orders/order-stock.ts'
import { transitionOrder, type OrderStatus } from '../../core/orders/order-status.ts'
import type { Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** The cancelled order beside the status it was cancelled from. */
type Cancellation = { order: Order; statusFrom: OrderStatus }

/**
 * Cancels an order that has not been paid, handing back the stock placement
 * took. A paid order has no route here — the transition table refuses it, and
 * the refusal closes the story with the world unchanged.
 */
export async function cancelOrder(context: ActionContext, orderId: OrderId): Promise<Order> {
  const cancellation = await actionStory<Cancellation>(
    context,
    {
      event: 'order.cancel',
      will: { msg: 'cancelling the order', data: { order_id: orderId } },
      ended: ({ order, statusFrom }) => ({
        phase: 'did',
        msg: 'cancelled the order',
        data: { order_id: order.id, status_from: statusFrom, status_to: order.status },
      }),
    },
    (transacted) => cancel(transacted, orderId),
  )

  return cancellation.order
}

async function cancel(transacted: ActionContext, orderId: OrderId): Promise<Cancellation> {
  const { db, clock } = transacted
  const order = await db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', orderId)
    .executeTakeFirstOrThrow()

  const status = transitionOrder(order.status, 'cancelled')

  await moveOrderStock(transacted, order.id, stockChangeBetween({ from: order.status, to: status }))

  const cancelled = await db
    .updateTable('orders')
    .set({ status, cancelledAt: toTimestamp(clock.now()) })
    .where('id', '=', order.id)
    .returningAll()
    .executeTakeFirstOrThrow()

  return { order: cancelled, statusFrom: order.status }
}

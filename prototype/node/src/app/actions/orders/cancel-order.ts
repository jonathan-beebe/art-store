import type { OrderId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { moveOrderStock } from './move-order-stock.ts'
import { stockChangeBetween } from '../../core/orders/order-stock.ts'
import { transitionOrder, type OrderStatus } from '../../core/orders/order-status.ts'
import { refused, type Refusal, type TransitionFacts } from '../../core/refusal.ts'
import type { Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** The facts a refused cancellation carries: the order and the move it asked for. */
type CancelRefusalFacts = { order_id: OrderId } & TransitionFacts<OrderStatus>

export type CancelOrderResult =
  | { outcome: 'cancelled'; order: Order }
  | Refusal<'illegal_transition', CancelRefusalFacts>

/** The internal result `cancel` reports: the cancelled order beside the
 * status it was cancelled from, or a refusal. */
type Cancellation =
  | { outcome: 'cancelled'; order: Order; statusFrom: OrderStatus }
  | Refusal<'illegal_transition', CancelRefusalFacts>

/**
 * Cancels an order that has not been paid, handing back the stock placement
 * took. A paid order has no route here — the transition table refuses it, and
 * the refusal is a returned result that closes the story with the world
 * unchanged.
 */
export async function cancelOrder(context: ActionContext, orderId: OrderId): Promise<CancelOrderResult> {
  const cancellation = await actionStory<Cancellation>(
    context,
    {
      event: 'order.cancel',
      will: { msg: 'cancelling the order', data: { order_id: orderId } },
      refusedMsg: 'the order cannot move to cancelled',
      ended: (result) => ({
        phase: 'did',
        msg: 'cancelled the order',
        data: { order_id: result.order.id, status_from: result.statusFrom, status_to: result.order.status },
      }),
    },
    (transacted) => cancel(transacted, orderId),
  )

  return cancellation.outcome === 'cancelled'
    ? { outcome: 'cancelled', order: cancellation.order }
    : cancellation
}

async function cancel(transacted: ActionContext, orderId: OrderId): Promise<Cancellation> {
  const { db, clock } = transacted
  const order = await db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', orderId)
    .executeTakeFirstOrThrow()

  const transition = transitionOrder(order.status, 'cancelled')
  if (transition.outcome === 'refused') {
    return refused('illegal_transition', { order_id: order.id, ...transition.data })
  }

  await moveOrderStock(
    transacted,
    order.id,
    stockChangeBetween({ from: order.status, to: transition.status }),
  )

  const cancelled = await db
    .updateTable('orders')
    .set({ status: transition.status, cancelledAt: toTimestamp(clock.now()) })
    .where('id', '=', order.id)
    .returningAll()
    .executeTakeFirstOrThrow()

  return { outcome: 'cancelled', order: cancelled, statusFrom: order.status }
}

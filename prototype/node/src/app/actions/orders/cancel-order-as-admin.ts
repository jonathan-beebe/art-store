import type { OrderId, SellerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { cancelOrder, type CancelOrderResult } from './cancel-order.ts'
import { notify } from '../notifications/notify.ts'
import { orderCancelledMessage } from '../../core/notifications/notification-message.ts'

export type CancelOrderAsAdminInput = {
  orderId: OrderId
  reason: string
}

/**
 * The platform cancels an unpaid order on someone's behalf. Nobody on either
 * side asked for it, so both the customer and every seller holding a piece of
 * it are told, with the reason the operator gave.
 */
export async function cancelOrderAsAdmin(
  context: ActionContext,
  input: CancelOrderAsAdminInput,
): Promise<CancelOrderResult> {
  return runInTransaction(context, async (transacted) => {
    const result = await cancelOrder(transacted, input.orderId)
    if (result.outcome === 'refused') return result

    const { order } = result

    await notify(transacted, {
      recipientType: 'customer',
      recipientId: order.customerId,
      message: orderCancelledMessage(order.id, input.reason, `/orders/${order.id}`),
    })

    for (const sellerId of await sellersOn(transacted, order.id)) {
      await notify(transacted, {
        recipientType: 'seller',
        recipientId: sellerId,
        message: orderCancelledMessage(order.id, input.reason),
      })
    }

    return result
  })
}

/** Every seller with a fulfillment on the order, each told once. */
async function sellersOn({ db }: ActionContext, orderId: OrderId): Promise<readonly SellerId[]> {
  const rows = await db
    .selectFrom('fulfillments')
    .select('sellerId')
    .distinct()
    .where('orderId', '=', orderId)
    .orderBy('sellerId')
    .execute()

  return rows.map((row) => row.sellerId)
}

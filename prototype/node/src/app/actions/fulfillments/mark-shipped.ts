import type { FulfillmentId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { notify } from '../notifications/notify.ts'
import { rollUpOrderStatus } from '../orders/roll-up-order-status.ts'
import { orderShippedMessage } from '../../core/notifications/notification-message.ts'
import { transitionFulfillment } from '../../core/orders/fulfillment-status.ts'
import type { Fulfillment } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type MarkShippedInput = {
  fulfillmentId: FulfillmentId
  carrier: string
  trackingNumber: string
}

/**
 * A seller's slice of an order leaves the studio. The order rolls up from every
 * fulfillment, so one of two shipping leaves the order partially shipped.
 */
export async function markShipped(
  context: ActionContext,
  input: MarkShippedInput,
): Promise<Fulfillment> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const fulfillment = await db
      .selectFrom('fulfillments')
      .selectAll()
      .where('id', '=', input.fulfillmentId)
      .executeTakeFirstOrThrow()

    const shipped = await db
      .updateTable('fulfillments')
      .set({
        status: transitionFulfillment(fulfillment.status, 'shipped'),
        carrier: input.carrier,
        trackingNumber: input.trackingNumber,
        shippedAt: toTimestamp(clock.now()),
      })
      .where('id', '=', fulfillment.id)
      .returningAll()
      .executeTakeFirstOrThrow()

    const order = await rollUpOrderStatus(transacted, fulfillment.orderId)

    await notify(transacted, {
      recipientType: 'customer',
      recipientId: order.customerId,
      message: orderShippedMessage(order.id, input.carrier, input.trackingNumber, `/orders/${order.id}`),
    })

    return shipped
  })
}

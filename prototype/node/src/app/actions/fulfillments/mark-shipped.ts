import type { FulfillmentId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
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
  return actionStory<Fulfillment>(
    context,
    {
      event: 'fulfillment.ship',
      will: {
        msg: 'marking the fulfillment shipped',
        data: { fulfillment_id: input.fulfillmentId, carrier: input.carrier },
      },
      ended: (shipped) => ({
        phase: 'did',
        msg: 'marked the fulfillment shipped',
        data: {
          fulfillment_id: shipped.id,
          order_id: shipped.orderId,
          seller_id: shipped.sellerId,
          status_from: 'awaiting_shipment',
          status_to: shipped.status,
          carrier: shipped.carrier,
          tracking_number: shipped.trackingNumber,
        },
      }),
    },
    async (transacted) => {
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
    },
  )
}

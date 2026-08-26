import type { FulfillmentId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { notify } from '../notifications/notify.ts'
import { rollUpOrderStatus } from '../orders/roll-up-order-status.ts'
import { orderShippedMessage } from '../../core/notifications/notification-message.ts'
import { transitionFulfillment } from '../../core/orders/fulfillment-status.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { Fulfillment } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type MarkShippedInput = {
  fulfillmentId: FulfillmentId
  carrier: string
  trackingNumber: string
}

export type MarkShippedResult = { outcome: 'shipped'; fulfillment: Fulfillment } | Refusal<'illegal_transition'>

/**
 * A seller's slice of an order leaves the studio. The order rolls up from every
 * fulfillment, so one of two shipping leaves the order partially shipped.
 */
export async function markShipped(
  context: ActionContext,
  input: MarkShippedInput,
): Promise<MarkShippedResult> {
  return actionStory<MarkShippedResult>(
    context,
    {
      event: 'fulfillment.ship',
      will: {
        msg: 'marking the fulfillment shipped',
        data: { fulfillment_id: input.fulfillmentId, carrier: input.carrier },
      },
      ended: (result) =>
        result.outcome === 'shipped'
          ? {
              phase: 'did',
              msg: 'marked the fulfillment shipped',
              data: {
                fulfillment_id: result.fulfillment.id,
                order_id: result.fulfillment.orderId,
                seller_id: result.fulfillment.sellerId,
                status_from: 'awaiting_shipment',
                status_to: result.fulfillment.status,
                carrier: result.fulfillment.carrier,
                tracking_number: result.fulfillment.trackingNumber,
              },
            }
          : {
              phase: 'refused',
              msg: 'the fulfillment cannot move to shipped',
              data: { reason: result.reason, ...result.data },
            },
    },
    async (transacted) => {
      const { db, clock } = transacted
      const fulfillment = await db
        .selectFrom('fulfillments')
        .selectAll()
        .where('id', '=', input.fulfillmentId)
        .executeTakeFirstOrThrow()

      const transition = transitionFulfillment(fulfillment.status, 'shipped')
      if (transition.outcome === 'refused') {
        return refused('illegal_transition', { fulfillment_id: fulfillment.id, ...transition.data })
      }

      const shipped = await db
        .updateTable('fulfillments')
        .set({
          status: transition.status,
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

      return { outcome: 'shipped', fulfillment: shipped }
    },
  )
}

/**
 * Unwraps a `MarkShippedResult` for a caller inside the application that only
 * ever asks for a legal move. A refusal reaching here is a broken contract,
 * not a domain outcome to handle.
 */
export function shippedFulfillment(result: MarkShippedResult): Fulfillment {
  if (result.outcome === 'shipped') return result.fulfillment

  throw new BrokenContractError(
    result.reason,
    `marking a fulfillment shipped was refused: ${result.reason}`,
    result.data,
  )
}

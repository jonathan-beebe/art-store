import type { FulfillmentId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { notify } from '../notifications/notify.ts'
import { rollUpOrderStatus } from '../orders/roll-up-order-status.ts'
import { orderShippedMessage } from '../../core/notifications/notification-message.ts'
import { transitionFulfillment, type FulfillmentStatus } from '../../core/orders/fulfillment-status.ts'
import { refused, type Refusal, type TransitionFacts } from '../../core/refusal.ts'
import type { Fulfillment } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type MarkShippedInput = {
  fulfillmentId: FulfillmentId
  carrier: string
  trackingNumber: string
}

export type MarkShippedResult =
  | { outcome: 'shipped'; fulfillment: Fulfillment }
  | Refusal<'illegal_transition', { fulfillment_id: FulfillmentId } & TransitionFacts<FulfillmentStatus>>

/** The internal result `ship` reports: the shipped fulfillment beside the
 * status it moved from, or a refusal. */
type Shipping =
  | { outcome: 'shipped'; fulfillment: Fulfillment; statusFrom: FulfillmentStatus }
  | Refusal<'illegal_transition', { fulfillment_id: FulfillmentId } & TransitionFacts<FulfillmentStatus>>

/**
 * A seller's slice of an order leaves the studio. The order rolls up from every
 * fulfillment, so one of two shipping leaves the order partially shipped.
 */
export async function markShipped(
  context: ActionContext,
  input: MarkShippedInput,
): Promise<MarkShippedResult> {
  const shipping = await actionStory<Shipping>(
    context,
    {
      event: 'fulfillment.ship',
      will: {
        msg: 'marking the fulfillment shipped',
        data: { fulfillment_id: input.fulfillmentId, carrier: input.carrier },
      },
      refusedMsg: 'the fulfillment cannot move to shipped',
      ended: (result) => ({
        phase: 'did',
        msg: 'marked the fulfillment shipped',
        data: {
          fulfillment_id: result.fulfillment.id,
          order_id: result.fulfillment.orderId,
          seller_id: result.fulfillment.sellerId,
          status_from: result.statusFrom,
          status_to: result.fulfillment.status,
          carrier: result.fulfillment.carrier,
          tracking_number: result.fulfillment.trackingNumber,
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

      return { outcome: 'shipped', fulfillment: shipped, statusFrom: fulfillment.status }
    },
  )

  return shipping.outcome === 'shipped' ? { outcome: 'shipped', fulfillment: shipping.fulfillment } : shipping
}

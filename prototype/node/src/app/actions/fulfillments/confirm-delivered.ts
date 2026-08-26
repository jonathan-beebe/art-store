import type { FulfillmentId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { writeLedgerEntry } from '../escrow/write-ledger-entry.ts'
import { rollUpOrderStatus } from '../orders/roll-up-order-status.ts'
import { releaseMovement } from '../../core/escrow/ledger-movement.ts'
import { transitionFulfillment, type FulfillmentStatus } from '../../core/orders/fulfillment-status.ts'
import { refused, type Refusal, type TransitionFacts } from '../../core/refusal.ts'
import type { Fulfillment } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type ConfirmDeliveredResult =
  | { outcome: 'delivered'; fulfillment: Fulfillment }
  | Refusal<'illegal_transition', { fulfillment_id: FulfillmentId } & TransitionFacts<FulfillmentStatus>>

/** The internal result `deliver` reports: the delivered fulfillment beside the
 * status it moved from, or a refusal. */
type Delivery =
  | { outcome: 'delivered'; fulfillment: Fulfillment; statusFrom: FulfillmentStatus }
  | Refusal<'illegal_transition', { fulfillment_id: FulfillmentId } & TransitionFacts<FulfillmentStatus>>

/**
 * The customer confirms the piece arrived, which is what releases the seller's
 * escrow. The released money is available to the next weekly payout.
 */
export async function confirmDelivered(
  context: ActionContext,
  fulfillmentId: FulfillmentId,
): Promise<ConfirmDeliveredResult> {
  const delivery = await actionStory<Delivery>(
    context,
    {
      event: 'fulfillment.deliver',
      will: {
        msg: 'confirming the fulfillment delivered',
        data: { fulfillment_id: fulfillmentId },
      },
      refusedMsg: 'the fulfillment cannot move to delivered',
      ended: (result) => ({
        phase: 'did',
        msg: 'confirmed the fulfillment delivered',
        data: {
          fulfillment_id: result.fulfillment.id,
          order_id: result.fulfillment.orderId,
          seller_id: result.fulfillment.sellerId,
          status_from: result.statusFrom,
          status_to: result.fulfillment.status,
          net_cents: result.fulfillment.netCents,
        },
      }),
    },
    async (transacted) => {
      const { db, clock } = transacted
      const now = toTimestamp(clock.now())
      const fulfillment = await db
        .selectFrom('fulfillments')
        .selectAll()
        .where('id', '=', fulfillmentId)
        .executeTakeFirstOrThrow()

      const transition = transitionFulfillment(fulfillment.status, 'delivered')
      if (transition.outcome === 'refused') {
        return refused('illegal_transition', { fulfillment_id: fulfillment.id, ...transition.data })
      }

      const delivered = await db
        .updateTable('fulfillments')
        .set({
          status: transition.status,
          deliveredAt: now,
        })
        .where('id', '=', fulfillment.id)
        .returningAll()
        .executeTakeFirstOrThrow()

      await writeLedgerEntry(
        transacted,
        {
          sellerId: fulfillment.sellerId,
          fulfillmentId: fulfillment.id,
          payoutId: null,
          movement: releaseMovement(fulfillment.netCents),
          occurredAt: now,
        },
        clock.now(),
      )

      await rollUpOrderStatus(transacted, fulfillment.orderId)

      return { outcome: 'delivered', fulfillment: delivered, statusFrom: fulfillment.status }
    },
  )

  return delivery.outcome === 'delivered'
    ? { outcome: 'delivered', fulfillment: delivery.fulfillment }
    : delivery
}

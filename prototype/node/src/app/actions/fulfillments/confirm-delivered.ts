import type { FulfillmentId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { writeLedgerEntry } from '../escrow/write-ledger-entry.ts'
import { rollUpOrderStatus } from '../orders/roll-up-order-status.ts'
import { releaseMovement } from '../../core/escrow/ledger-movement.ts'
import { transitionFulfillment } from '../../core/orders/fulfillment-status.ts'
import type { Fulfillment } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/**
 * The customer confirms the piece arrived, which is what releases the seller's
 * escrow. The released money is available to the next weekly payout.
 */
export async function confirmDelivered(
  context: ActionContext,
  fulfillmentId: FulfillmentId,
): Promise<Fulfillment> {
  return actionStory<Fulfillment>(
    context,
    {
      event: 'fulfillment.deliver',
      will: {
        msg: 'confirming the fulfillment delivered',
        data: { fulfillment_id: fulfillmentId },
      },
      ended: (delivered) => ({
        phase: 'did',
        msg: 'confirmed the fulfillment delivered',
        data: {
          fulfillment_id: delivered.id,
          order_id: delivered.orderId,
          seller_id: delivered.sellerId,
          status_from: 'shipped',
          status_to: delivered.status,
          net_cents: delivered.netCents,
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

      const delivered = await db
        .updateTable('fulfillments')
        .set({
          status: transitionFulfillment(fulfillment.status, 'delivered'),
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

      return delivered
    },
  )
}

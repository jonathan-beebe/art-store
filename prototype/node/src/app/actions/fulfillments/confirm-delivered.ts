import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
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
  fulfillmentId: number,
): Promise<Fulfillment> {
  return runInTransaction(context, async (transacted) => {
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

    const release = releaseMovement(fulfillment.netCents)

    await db
      .insertInto('ledgerEntries')
      .values({
        sellerId: fulfillment.sellerId,
        fulfillmentId: fulfillment.id,
        payoutId: null,
        entryType: release.entryType,
        amountCents: release.amountCents,
        occurredAt: now,
      })
      .execute()

    await rollUpOrderStatus(transacted, fulfillment.orderId)

    return delivered
  })
}

import type { ActionContext } from '../action-context.ts'
import { actionStep, actionStory } from '../action-story.ts'
import { cancelOrder } from './cancel-order.ts'
import { mustSucceed } from '../../core/refusal.ts'
import { SWEEPABLE_ORDER_STATUS, staleOrderCutoff } from '../../core/orders/stale-order.ts'
import type { Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type SweepStaleOrdersInput = {
  /** How old an unverified order has to be before the sweep cancels it. */
  staleHours: number
  asOf: Date
}

/**
 * Cancels the orders a visitor placed and never verified, handing their stock
 * back to the storefront. Only `pending_verification` is swept — an order that
 * reached `awaiting_payment` has a verified customer behind it — and only rows
 * older than the cutoff, so a second run over the same window cancels nothing
 * because the first run already moved them out of the status it reads.
 */
export async function sweepStaleOrders(
  context: ActionContext,
  input: SweepStaleOrdersInput,
): Promise<readonly Order[]> {
  const cutoff = staleOrderCutoff(input.asOf, input.staleHours)

  return actionStory<readonly Order[]>(
    context,
    {
      event: 'order.sweep',
      will: {
        msg: `sweeping orders left unverified since ${cutoff.toISOString()}`,
        data: { cutoff: toTimestamp(cutoff), stale_hours: input.staleHours },
      },
      ended: (cancelled) => ({
        phase: 'did',
        msg:
          cancelled.length === 0
            ? 'no order has been left unverified past the cutoff'
            : `${cancelled.length} stale order(s) cancelled`,
        data: { cutoff: toTimestamp(cutoff), count: cancelled.length },
      }),
    },
    async (transacted) => {
      const stale = await transacted.db
        .selectFrom('orders')
        .select('id')
        .where('status', '=', SWEEPABLE_ORDER_STATUS)
        .where('placedAt', '<', toTimestamp(cutoff))
        .orderBy('placedAt')
        .orderBy('id')
        .execute()

      const cancelled: Order[] = []
      for (const row of stale) {
        actionStep(transacted, 'order.sweep', {
          msg: 'cancelling a stale order',
          data: { order_id: row.id },
        })
        cancelled.push(mustSucceed(await cancelOrder(transacted, row.id)).order)
      }

      return cancelled
    },
  )
}

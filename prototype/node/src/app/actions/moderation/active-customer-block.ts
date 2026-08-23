import type { ActionContext } from '../action-context.ts'
import { activeBlock } from '../../core/moderation/customer-standing.ts'
import { fromNullableTimestamp, fromTimestamp } from '../../db/timestamp.ts'

/** A block as a page shows it: enough to name the reason and offer the lift. */
export type ActiveCustomerBlock = {
  id: number
  reason: string
  createdAt: Date
  liftedAt: Date | null
}

/**
 * The unlifted block on a customer, if any. Pages that only ask whether the
 * customer may shop read `currentCustomerStanding` instead; this is for the
 * admin site, which acts on the row.
 */
export async function activeCustomerBlock(
  { db }: Pick<ActionContext, 'db'>,
  customerId: number,
): Promise<ActiveCustomerBlock | null> {
  const blocks = await db
    .selectFrom('customerBlocks')
    .select(['id', 'reason', 'createdAt', 'liftedAt'])
    .where('customerId', '=', customerId)
    .where('liftedAt', 'is', null)
    .orderBy('id')
    .execute()

  return activeBlock(
    blocks.map((block) => ({
      ...block,
      createdAt: fromTimestamp(block.createdAt),
      liftedAt: fromNullableTimestamp(block.liftedAt),
    })),
  )
}

import type { ActionContext } from '../action-context.ts'
import { customerStanding, type CustomerStanding } from '../../core/moderation/customer-standing.ts'
import { fromNullableTimestamp } from '../../db/timestamp.ts'

/**
 * Whether a customer may add to a cart, check out, and send messages. A block
 * leaves browsing alone, so every write path reads this and every read path
 * ignores it.
 */
export async function currentCustomerStanding(
  { db }: Pick<ActionContext, 'db'>,
  customerId: number,
): Promise<CustomerStanding> {
  const blocks = await db
    .selectFrom('customerBlocks')
    .select(['reason', 'liftedAt'])
    .where('customerId', '=', customerId)
    .orderBy('id')
    .execute()

  return customerStanding(
    blocks.map((block) => ({ ...block, liftedAt: fromNullableTimestamp(block.liftedAt) })),
  )
}

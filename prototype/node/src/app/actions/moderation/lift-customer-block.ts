import type { CustomerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { activeCustomerBlock } from './active-customer-block.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { CustomerBlock } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type LiftCustomerBlockResult = { outcome: 'lifted'; block: CustomerBlock } | Refusal<'not_blocked'>

/** Hands a blocked customer their cart, checkout, and messages back. */
export async function liftCustomerBlock(
  context: ActionContext,
  { customerId }: { customerId: CustomerId },
): Promise<LiftCustomerBlockResult> {
  return actionStory<LiftCustomerBlockResult>(
    context,
    {
      event: 'moderation.lift_customer_block',
      will: { msg: 'lifting the block on the customer', data: { customer_id: customerId } },
      refusedMsg: 'the block cannot be lifted',
      ended: (result) => ({
        phase: 'did',
        msg: 'lifted the block on the customer',
        data: { customer_block_id: result.block.id, customer_id: result.block.customerId },
      }),
    },
    async (transacted) => {
      const { db, clock } = transacted
      const active = await activeCustomerBlock(transacted, customerId)

      if (active === null) return refused('not_blocked', { customer_id: customerId })

      const block = await db
        .updateTable('customerBlocks')
        .set({ liftedAt: toTimestamp(clock.now()) })
        .where('id', '=', active.id)
        .returningAll()
        .executeTakeFirstOrThrow()

      return { outcome: 'lifted', block }
    },
  )
}

/**
 * Unwraps a `LiftCustomerBlockResult` for a caller inside the application
 * that only ever asks to lift a block that is active. A refusal reaching
 * here is a broken contract, not a domain outcome to handle.
 */
export function liftedCustomerBlock(result: LiftCustomerBlockResult): CustomerBlock {
  if (result.outcome === 'lifted') return result.block

  throw new BrokenContractError(
    result.reason,
    `a customer-block lift was refused: ${result.reason}`,
    result.data,
  )
}

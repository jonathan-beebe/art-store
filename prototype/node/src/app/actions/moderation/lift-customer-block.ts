import type { CustomerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { activeCustomerBlock } from './active-customer-block.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { CustomerBlock } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** Hands a blocked customer their cart, checkout, and messages back. */
export async function liftCustomerBlock(
  context: ActionContext,
  { customerId }: { customerId: CustomerId },
): Promise<CustomerBlock> {
  return actionStory<CustomerBlock>(
    context,
    {
      event: 'moderation.lift_customer_block',
      will: { msg: 'lifting the block on the customer', data: { customer_id: customerId } },
      ended: (block) => ({
        phase: 'did',
        msg: 'lifted the block on the customer',
        data: { customer_block_id: block.id, customer_id: block.customerId },
      }),
    },
    async (transacted) => {
      const { db, clock } = transacted
      const active = await activeCustomerBlock(transacted, customerId)

      if (active === null) throw new TransitionError(`customer ${customerId} is not blocked`)

      return db
        .updateTable('customerBlocks')
        .set({ liftedAt: toTimestamp(clock.now()) })
        .where('id', '=', active.id)
        .returningAll()
        .executeTakeFirstOrThrow()
    },
  )
}

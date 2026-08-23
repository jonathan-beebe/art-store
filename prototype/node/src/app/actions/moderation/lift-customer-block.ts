import type { ActionContext } from '../action-context.ts'
import { activeCustomerBlock } from './active-customer-block.ts'
import { runInTransaction } from '../transaction.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { CustomerBlock } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** Hands a blocked customer their cart, checkout, and messages back. */
export async function liftCustomerBlock(
  context: ActionContext,
  { customerId }: { customerId: number },
): Promise<CustomerBlock> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const active = await activeCustomerBlock(transacted, customerId)

    if (active === null) throw new TransitionError(`customer ${customerId} is not blocked`)

    return db
      .updateTable('customerBlocks')
      .set({ liftedAt: toTimestamp(clock.now()) })
      .where('id', '=', active.id)
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

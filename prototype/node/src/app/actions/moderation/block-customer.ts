import type { AdminId, CustomerId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { activeCustomerBlock } from './active-customer-block.ts'
import { runInTransaction } from '../transaction.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { CustomerBlock } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type BlockCustomerInput = {
  customerId: CustomerId
  adminId: AdminId
  reason: string
}

/**
 * Stops a customer buying and messaging. Browsing is deliberately left alone,
 * which is why one block at a time is enough: the reason a page shows is the
 * one active row.
 */
export async function blockCustomer(
  context: ActionContext,
  input: BlockCustomerInput,
): Promise<CustomerBlock> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const active = await activeCustomerBlock(transacted, input.customerId)

    if (active !== null) {
      throw new TransitionError(`customer ${input.customerId} is already blocked`)
    }

    return db
      .insertInto('customerBlocks')
      .values({
        id: newId('blk', clock.now()),
        customerId: input.customerId,
        adminId: input.adminId,
        reason: input.reason,
        createdAt: toTimestamp(clock.now()),
        liftedAt: null,
      })
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

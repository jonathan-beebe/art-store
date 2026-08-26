import type { AdminId, CustomerId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { activeCustomerBlock } from './active-customer-block.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { CustomerBlock } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type BlockCustomerInput = {
  customerId: CustomerId
  adminId: AdminId
  reason: string
}

export type BlockCustomerResult =
  | { outcome: 'blocked'; block: CustomerBlock }
  | Refusal<'already_blocked'>

/**
 * Stops a customer buying and messaging. Browsing is deliberately left alone,
 * which is why one block at a time is enough: the reason a page shows is the
 * one active row.
 */
export async function blockCustomer(
  context: ActionContext,
  input: BlockCustomerInput,
): Promise<BlockCustomerResult> {
  return actionStory<BlockCustomerResult>(
    context,
    {
      event: 'moderation.block_customer',
      will: {
        msg: 'blocking the customer from buying and messaging',
        data: { customer_id: input.customerId },
      },
      refusedMsg: 'the customer cannot be blocked',
      ended: (result) => ({
        phase: 'did',
        msg: 'blocked the customer from buying and messaging',
        data: {
          customer_block_id: result.block.id,
          customer_id: result.block.customerId,
          admin_id: result.block.adminId,
        },
      }),
    },
    async (transacted) => {
      const { db, clock } = transacted
      const active = await activeCustomerBlock(transacted, input.customerId)

      if (active !== null) {
        return refused('already_blocked', {
          customer_id: input.customerId,
          customer_block_id: active.id,
        })
      }

      const block = await db
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

      return { outcome: 'blocked', block }
    },
  )
}

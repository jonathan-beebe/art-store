import type { ActionContext } from '../action-context.ts'
import { activeListingRemoval } from './active-listing-removal.ts'
import { runInTransaction } from '../transaction.ts'
import type { RemovalKind } from '../../core/moderation/listing-removal.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { ListingRemoval } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type RemoveListingInput = {
  listingId: number
  adminId: number
  kind: RemovalKind
  reason: string
}

/**
 * Takes a listing off the storefront whatever its status. At most one removal
 * is active at a time, so raising a temporary removal to a permanent one is
 * lift then remove — which leaves the seller one reason to read rather than
 * two overlapping ones.
 */
export async function removeListing(
  context: ActionContext,
  input: RemoveListingInput,
): Promise<ListingRemoval> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const active = await activeListingRemoval(transacted, input.listingId)

    if (active !== null) {
      throw new TransitionError(`listing ${input.listingId} is already removed`)
    }

    return db
      .insertInto('listingRemovals')
      .values({
        listingId: input.listingId,
        adminId: input.adminId,
        kind: input.kind,
        reason: input.reason,
        createdAt: toTimestamp(clock.now()),
        liftedAt: null,
      })
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

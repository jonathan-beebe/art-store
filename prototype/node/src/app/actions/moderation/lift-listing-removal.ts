import type { ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { activeListingRemoval } from './active-listing-removal.ts'
import { runInTransaction } from '../transaction.ts'
import { canLiftRemoval } from '../../core/moderation/listing-removal.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { ListingRemoval } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/**
 * Puts a temporarily removed listing back under its own status. A permanent
 * removal is refused here rather than hidden in the page that offers it, so a
 * stale form cannot undo one.
 */
export async function liftListingRemoval(
  context: ActionContext,
  { listingId }: { listingId: ListingId },
): Promise<ListingRemoval> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const active = await activeListingRemoval(transacted, listingId)

    if (active === null) throw new TransitionError(`listing ${listingId} is not removed`)
    if (!canLiftRemoval(active.kind)) {
      throw new TransitionError(`a permanent removal cannot be lifted`)
    }

    return db
      .updateTable('listingRemovals')
      .set({ liftedAt: toTimestamp(clock.now()) })
      .where('id', '=', active.id)
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

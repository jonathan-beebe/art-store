import type { ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { activeListingRemoval } from '../moderation/active-listing-removal.ts'
import { runInTransaction } from '../transaction.ts'
import { isBlockedByRemoval, transitionListing, type ListingStatus } from '../../core/listings/listing-status.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { Listing } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type ChangeListingStatusInput = {
  listingId: ListingId
  status: ListingStatus
}

const REMOVED_LISTING_MESSAGE = 'This listing was removed by an admin and cannot be put back on sale.'

/**
 * Moves a listing through its lifecycle, refusing anything the table forbids.
 * An active admin removal refuses a return to `for_sale` regardless of caller
 * — the rule lives here rather than in each route that changes a status.
 */
export async function changeListingStatus(
  context: ActionContext,
  input: ChangeListingStatusInput,
): Promise<Listing> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const listing = await db
      .selectFrom('listings')
      .selectAll()
      .where('id', '=', input.listingId)
      .executeTakeFirstOrThrow()

    const removal = await activeListingRemoval(transacted, listing.id)
    if (isBlockedByRemoval(input.status, removal !== null)) {
      throw new TransitionError(REMOVED_LISTING_MESSAGE)
    }

    return db
      .updateTable('listings')
      .set({
        status: transitionListing(listing.status, input.status),
        updatedAt: toTimestamp(clock.now()),
      })
      .where('id', '=', listing.id)
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

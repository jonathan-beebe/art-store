import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { transitionListing, type ListingStatus } from '../../core/listings/listing-status.ts'
import type { Listing } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type ChangeListingStatusInput = {
  listingId: number
  status: ListingStatus
}

/** Moves a listing through its lifecycle, refusing anything the table forbids. */
export async function changeListingStatus(
  context: ActionContext,
  input: ChangeListingStatusInput,
): Promise<Listing> {
  return runInTransaction(context, async ({ db, clock }) => {
    const listing = await db
      .selectFrom('listings')
      .selectAll()
      .where('id', '=', input.listingId)
      .executeTakeFirstOrThrow()

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

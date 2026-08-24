import type { ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
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

/** Putting a piece on the storefront is the move worth its own name. */
const PUBLISHED_STATUS: ListingStatus = 'for_sale'

/**
 * Moves a listing through its lifecycle, refusing anything the table forbids.
 * An active admin removal refuses a return to `for_sale` regardless of caller
 * — the rule lives here rather than in each route that changes a status.
 *
 * The status being asked for names the event, so the read that finds the
 * listing runs before the story opens and both lines carry `status_from`.
 */
export async function changeListingStatus(
  context: ActionContext,
  input: ChangeListingStatusInput,
): Promise<Listing> {
  return runInTransaction(context, async (transacted) => {
    const { db } = transacted
    const listing = await db
      .selectFrom('listings')
      .selectAll()
      .where('id', '=', input.listingId)
      .executeTakeFirstOrThrow()

    const transition = {
      listing_id: listing.id,
      seller_id: listing.sellerId,
      status_from: listing.status,
      status_to: input.status,
    }

    return actionStory<Listing>(
      transacted,
      {
        event: input.status === PUBLISHED_STATUS ? 'listing.publish' : 'listing.transition',
        will: { msg: `moving the listing to ${input.status}`, data: transition },
        ended: (moved) => ({
          phase: 'did',
          msg: `moved the listing to ${moved.status}`,
          data: transition,
        }),
      },
      (writing) => moveTo(writing, listing, input.status),
    )
  })
}

async function moveTo(
  context: ActionContext,
  listing: Listing,
  status: ListingStatus,
): Promise<Listing> {
  const { db, clock } = context
  const removal = await activeListingRemoval(context, listing.id)
  if (isBlockedByRemoval(status, removal !== null)) {
    throw new TransitionError(REMOVED_LISTING_MESSAGE)
  }

  return db
    .updateTable('listings')
    .set({
      status: transitionListing(listing.status, status),
      updatedAt: toTimestamp(clock.now()),
    })
    .where('id', '=', listing.id)
    .returningAll()
    .executeTakeFirstOrThrow()
}

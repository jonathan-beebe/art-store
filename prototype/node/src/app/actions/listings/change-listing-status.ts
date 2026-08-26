import type { ListingId, SellerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { activeListingRemoval } from '../moderation/active-listing-removal.ts'
import { runInTransaction } from '../transaction.ts'
import { isBlockedByRemoval, transitionListing, type ListingStatus } from '../../core/listings/listing-status.ts'
import {
  refused,
  type IllegalTransition,
  type Refusal,
  type TransitionFacts,
} from '../../core/refusal.ts'
import type { Listing } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type ChangeListingStatusInput = {
  listingId: ListingId
  status: ListingStatus
}

/** The facts a refused status change carries: the listing, its seller, and the
 * move it asked for. */
type ListingMoveFacts = { listing_id: ListingId; seller_id: SellerId } & TransitionFacts<ListingStatus>

export type ListingStatusChange =
  | { outcome: 'changed'; listing: Listing }
  | Refusal<'illegal_transition', ListingMoveFacts>
  | Refusal<'listing_removed', ListingMoveFacts>

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
): Promise<ListingStatusChange> {
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

    return actionStory<ListingStatusChange>(
      transacted,
      {
        event: input.status === PUBLISHED_STATUS ? 'listing.publish' : 'listing.transition',
        will: { msg: `moving the listing to ${input.status}`, data: transition },
        refusedMsg: `the listing cannot move to ${input.status}`,
        ended: (result) => ({
          phase: 'did',
          msg: `moved the listing to ${result.listing.status}`,
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
): Promise<ListingStatusChange> {
  const { db, clock } = context
  const removal = await activeListingRemoval(context, listing.id)
  if (isBlockedByRemoval(status, removal !== null)) {
    return refused('listing_removed', {
      listing_id: listing.id,
      seller_id: listing.sellerId,
      status_from: listing.status,
      status_to: status,
    })
  }

  const transition = transitionListing(listing.status, status)
  if (transition.outcome === 'refused') {
    return refused('illegal_transition', { listing_id: listing.id, seller_id: listing.sellerId, ...transition.data })
  }

  const updated = await db
    .updateTable('listings')
    .set({
      status: transition.status,
      updatedAt: toTimestamp(clock.now()),
    })
    .where('id', '=', listing.id)
    .returningAll()
    .executeTakeFirstOrThrow()

  return { outcome: 'changed', listing: updated }
}

/** The sentence a refused status change shows beside the button, the same on
 * every site that takes one. */
export function listingStatusRefusalCopy(
  refusal: IllegalTransition<ListingStatus> | Refusal<'listing_removed'>,
): string {
  if (refusal.reason === 'listing_removed') {
    return 'This listing was removed by an admin and cannot be put back on sale.'
  }

  const { status_from, status_to } = refusal.data

  return `A listing cannot move from ${status_from} to ${status_to}.`
}

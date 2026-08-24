import type { CustomerId, ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { recordListingEvent } from './record-listing-event.ts'
import type { ListingEvent } from '../../db/commerce-schema.ts'

export type RecordListingViewInput = {
  listingId: ListingId
  customerId: CustomerId
}

/**
 * One storefront view of a listing. The core collapses views to one per
 * (listing, customer, hour), so a refresh is a `refused` line rather than a
 * second row — at `debug`, because a busy listing page would otherwise be most
 * of the log.
 */
export async function recordListingView(
  context: ActionContext,
  input: RecordListingViewInput,
): Promise<ListingEvent | null> {
  return actionStory<ListingEvent | null>(
    context,
    {
      event: 'listing.view',
      level: 'debug',
      will: {
        msg: 'recording a view of the listing',
        data: { listing_id: input.listingId, customer_id: input.customerId },
      },
      ended: (event) =>
        event === null
          ? {
              phase: 'refused',
              msg: 'this customer already viewed the listing this hour',
              data: { listing_id: input.listingId, customer_id: input.customerId },
            }
          : {
              phase: 'did',
              msg: 'recorded a view of the listing',
              data: { listing_event_id: event.id, listing_id: event.listingId },
            },
    },
    (transacted) => recordListingEvent(transacted, { ...input, eventType: 'view' }),
  )
}

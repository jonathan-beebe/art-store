import type { ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import type { Listing } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type UpdateListingInput = {
  listingId: ListingId
  draft: ListingDraft
  /** Left out, the listing keeps the image it has. */
  imagePath?: string | null
}

/**
 * Writes the edited fields. The slug stays as it was, so a retitled listing
 * keeps the storefront URL it was shared under, and so does the status.
 */
export async function updateListing(
  context: ActionContext,
  input: UpdateListingInput,
): Promise<Listing> {
  return actionStory<Listing>(
    context,
    {
      event: 'listing.update',
      will: { msg: 'editing the listing', data: { listing_id: input.listingId } },
      ended: (listing) => ({
        phase: 'did',
        msg: 'edited the listing',
        data: {
          listing_id: listing.id,
          seller_id: listing.sellerId,
          title: listing.title,
          price_cents: listing.priceCents,
          quantity: listing.quantity,
        },
      }),
    },
    async ({ db, clock }) => {
      const image = input.imagePath === undefined ? {} : { imagePath: input.imagePath }

      return db
        .updateTable('listings')
        .set({ ...input.draft, ...image, updatedAt: toTimestamp(clock.now()) })
        .where('id', '=', input.listingId)
        .returningAll()
        .executeTakeFirstOrThrow()
    },
  )
}

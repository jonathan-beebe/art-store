import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import type { Listing } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type UpdateListingInput = {
  listingId: number
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
  return runInTransaction(context, async ({ db, clock }) => {
    const image = input.imagePath === undefined ? {} : { imagePath: input.imagePath }

    return db
      .updateTable('listings')
      .set({ ...input.draft, ...image, updatedAt: toTimestamp(clock.now()) })
      .where('id', '=', input.listingId)
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

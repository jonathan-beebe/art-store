import type { SellerId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import { firstFreeSlug, slugBase } from '../../core/listings/listing-slug.ts'
import type { Listing } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type CreateListingInput = {
  sellerId: SellerId
  draft: ListingDraft
  /** Where the uploaded file landed under `public/`; null shows a generated placeholder. */
  imagePath?: string | null
}

/**
 * Opens a listing as a draft — the status the column defaults to. The seller
 * decides when it goes on sale, so nothing here touches status.
 */
export async function createListing(
  context: ActionContext,
  input: CreateListingInput,
): Promise<Listing> {
  return runInTransaction(context, async ({ db, clock }) => {
    const now = toTimestamp(clock.now())
    const taken = await db
      .selectFrom('listings')
      .select('slug')
      .where('slug', 'like', `${slugBase(input.draft.title)}%`)
      .execute()

    return db
      .insertInto('listings')
      .values({
        id: newId('lst', clock.now()),
        ...input.draft,
        sellerId: input.sellerId,
        slug: firstFreeSlug(
          input.draft.title,
          taken.map((row) => row.slug),
        ),
        imagePath: input.imagePath ?? null,
        createdAt: now,
        updatedAt: now,
      })
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

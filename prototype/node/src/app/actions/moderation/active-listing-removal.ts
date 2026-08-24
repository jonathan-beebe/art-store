import type { ListingId, ListingRemovalId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { activeRemoval, type RemovalKind } from '../../core/moderation/listing-removal.ts'
import { fromNullableTimestamp, fromTimestamp } from '../../db/timestamp.ts'

/** A removal as a page shows it: enough to name the reason and offer the lift. */
export type ActiveListingRemoval = {
  id: ListingRemovalId
  kind: RemovalKind
  reason: string
  createdAt: Date
  liftedAt: Date | null
}

/**
 * The unlifted removal on a listing, if any. Storefront and portal pages hand
 * the answer to `isOnStorefront` / `isPurchasable`; the admin site writes the
 * rows.
 */
export async function activeListingRemoval(
  { db }: Pick<ActionContext, 'db'>,
  listingId: ListingId,
): Promise<ActiveListingRemoval | null> {
  const removals = await db
    .selectFrom('listingRemovals')
    .select(['id', 'kind', 'reason', 'createdAt', 'liftedAt'])
    .where('listingId', '=', listingId)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  return activeRemoval(
    removals.map((removal) => ({
      ...removal,
      createdAt: fromTimestamp(removal.createdAt),
      liftedAt: fromNullableTimestamp(removal.liftedAt),
    })),
  )
}

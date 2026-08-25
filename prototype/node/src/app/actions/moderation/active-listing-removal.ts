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

/**
 * Which of these listings currently sit under an unlifted removal. The cart
 * and checkout judge a whole set of lines at once, so this answers the same
 * question `activeListingRemoval` does per listing, in one query.
 */
export async function listingsUnderActiveRemoval(
  context: Pick<ActionContext, 'db'>,
  listingIds: readonly ListingId[],
): Promise<ReadonlySet<ListingId>> {
  if (listingIds.length === 0) return new Set()

  const rows = await context.db
    .selectFrom('listingRemovals')
    .select('listingId')
    .where('listingId', 'in', listingIds)
    .where('liftedAt', 'is', null)
    .execute()

  return new Set(rows.map((row) => row.listingId))
}

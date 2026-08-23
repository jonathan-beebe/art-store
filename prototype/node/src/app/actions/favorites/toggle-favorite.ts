import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { recordListingEvent } from '../listings/record-listing-event.ts'
import {
  favoriteChangeFor,
  listingEventForFavoriteChange,
  type FavoriteChange,
} from '../../core/listings/favorite-change.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type ToggleFavoriteInput = {
  customerId: number
  listingId: number
}

/** One button saves and unsaves, and either way the listing records the event. */
export async function toggleFavorite(
  context: ActionContext,
  input: ToggleFavoriteInput,
): Promise<FavoriteChange> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const saved = await db
      .selectFrom('favorites')
      .select('id')
      .where('customerId', '=', input.customerId)
      .where('listingId', '=', input.listingId)
      .executeTakeFirst()

    const change = favoriteChangeFor(saved !== undefined)

    if (change === 'added') {
      await db
        .insertInto('favorites')
        .values({ ...input, createdAt: toTimestamp(clock.now()) })
        .execute()
    } else {
      await db
        .deleteFrom('favorites')
        .where('customerId', '=', input.customerId)
        .where('listingId', '=', input.listingId)
        .execute()
    }

    await recordListingEvent(transacted, {
      listingId: input.listingId,
      customerId: input.customerId,
      eventType: listingEventForFavoriteChange(change),
    })

    return change
  })
}

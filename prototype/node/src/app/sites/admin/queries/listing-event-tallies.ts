import type { ActionContext } from '../../../actions/action-context.ts'
import { tallyOver, type Tally } from '../../../core/analytics/tally.ts'
import {
  LISTING_EVENT_TYPES,
  type ListingEventType,
} from '../../../core/listings/listing-event-type.ts'
import { toCount } from '../../../db/count.ts'

/** How much the storefront looked, favorited, and added to a cart. */
export async function listingEventTallies({
  db,
}: Pick<ActionContext, 'db'>): Promise<readonly Tally<ListingEventType>[]> {
  const rows = await db
    .selectFrom('listingEvents')
    .select(['eventType'])
    .select((eb) => eb.fn.countAll<string | number | bigint>().as('count'))
    .groupBy('eventType')
    .execute()

  return tallyOver(
    LISTING_EVENT_TYPES,
    rows.map((row) => ({ key: row.eventType, count: toCount(row.count) })),
  )
}

import type { AppDatabase } from '../../../db/database.ts'
import type { ListingEventType } from '../../../core/listings/listing-event-type.ts'
import { toTimestamp, type Timestamp } from '../../../db/timestamp.ts'

export async function listingEventTotals(
  db: AppDatabase,
  listingId: number,
): Promise<Partial<Record<ListingEventType, number>>> {
  const rows = await db
    .selectFrom('listingEvents')
    .select(['eventType', (eb) => eb.fn.countAll().as('count')])
    .where('listingId', '=', listingId)
    .groupBy('eventType')
    .execute()

  return Object.fromEntries(rows.map((row) => [row.eventType, Number(row.count)]))
}

/** Every event the listing recorded on or after `since`, grouped by the UTC
 * day it occurred on and then by event type — what `activityTimeline` reads. */
export async function listingEventCountsByDay(
  db: AppDatabase,
  listingId: number,
  since: Date,
): Promise<Record<string, Partial<Record<ListingEventType, number>>>> {
  const rows = await db
    .selectFrom('listingEvents')
    .select(['occurredAt', 'eventType'])
    .where('listingId', '=', listingId)
    .where('occurredAt', '>=', toTimestamp(since))
    .execute()

  const byDay: Record<string, Partial<Record<ListingEventType, number>>> = {}
  for (const row of rows) {
    const day = row.occurredAt.slice(0, 10)
    const forDay = byDay[day] ?? {}
    byDay[day] = { ...forDay, [row.eventType]: (forDay[row.eventType] ?? 0) + 1 }
  }

  return byDay
}

export type ListingSale = {
  orderId: number
  orderStatus: string
  placedAt: Timestamp
  quantity: number
  unitPriceCents: number
}

export async function salesForListing(db: AppDatabase, listingId: number): Promise<readonly ListingSale[]> {
  return db
    .selectFrom('orderItems')
    .innerJoin('orders', 'orders.id', 'orderItems.orderId')
    .select([
      'orderItems.orderId as orderId',
      'orders.status as orderStatus',
      'orders.placedAt as placedAt',
      'orderItems.quantity as quantity',
      'orderItems.unitPriceCents as unitPriceCents',
    ])
    .where('orderItems.listingId', '=', listingId)
    .orderBy('orderItems.id', 'desc')
    .execute()
}

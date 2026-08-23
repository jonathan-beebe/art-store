import type { AppDatabase } from '../../../db/database.ts'
import type { Listing } from '../../../db/commerce-schema.ts'
import type { ListingEventType } from '../../../core/listings/listing-event-type.ts'
import type { ListingStatus } from '../../../core/listings/listing-status.ts'

export async function listingsForSeller(db: AppDatabase, sellerId: number): Promise<readonly Listing[]> {
  return db.selectFrom('listings').selectAll().where('sellerId', '=', sellerId).orderBy('id', 'desc').execute()
}

export async function ownedListing(
  db: AppDatabase,
  sellerId: number,
  listingId: number,
): Promise<Listing | null> {
  const listing = await db
    .selectFrom('listings')
    .selectAll()
    .where('id', '=', listingId)
    .where('sellerId', '=', sellerId)
    .executeTakeFirst()

  return listing ?? null
}

export async function listingStatusCounts(
  db: AppDatabase,
  sellerId: number,
): Promise<Partial<Record<ListingStatus, number>>> {
  const rows = await db
    .selectFrom('listings')
    .select(['status', (eb) => eb.fn.countAll().as('count')])
    .where('sellerId', '=', sellerId)
    .groupBy('status')
    .execute()

  return Object.fromEntries(rows.map((row) => [row.status, Number(row.count)]))
}

/** Every listing's activity counts in one read, keyed by listing id then event type. */
export async function listingEventCountsByListing(
  db: AppDatabase,
  listingIds: readonly number[],
): Promise<ReadonlyMap<number, Partial<Record<ListingEventType, number>>>> {
  const counts = new Map<number, Partial<Record<ListingEventType, number>>>()
  if (listingIds.length === 0) return counts

  const rows = await db
    .selectFrom('listingEvents')
    .select(['listingId', 'eventType', (eb) => eb.fn.countAll().as('count')])
    .where('listingId', 'in', listingIds)
    .groupBy(['listingId', 'eventType'])
    .execute()

  for (const row of rows) {
    const forListing = counts.get(row.listingId) ?? {}
    counts.set(row.listingId, { ...forListing, [row.eventType]: Number(row.count) })
  }

  return counts
}

/** Which of these listings currently sit under an admin removal — enough to
 * decide whether the portal offers `for_sale` as a next status. */
export async function listingIdsWithActiveRemoval(
  db: AppDatabase,
  listingIds: readonly number[],
): Promise<ReadonlySet<number>> {
  if (listingIds.length === 0) return new Set()

  const rows = await db
    .selectFrom('listingRemovals')
    .select('listingId')
    .where('listingId', 'in', listingIds)
    .where('liftedAt', 'is', null)
    .execute()

  return new Set(rows.map((row) => row.listingId))
}

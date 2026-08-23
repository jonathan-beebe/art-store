import { LISTING_STATUSES, type ListingStatus } from '../listings/listing-status.ts'

export type ListingStatusCount = { status: ListingStatus; count: number }

/**
 * Every listing status a seller owns, in lifecycle order, so a dashboard tile
 * stays in place on a day nothing sold.
 */
export function listingStatusTally(
  countsByStatus: Partial<Record<ListingStatus, number>>,
): readonly ListingStatusCount[] {
  return LISTING_STATUSES.map((status) => ({ status, count: countsByStatus[status] ?? 0 }))
}

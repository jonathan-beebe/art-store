import type { ActionContext } from '../../../actions/action-context.ts'
import type { Cents } from '../../../core/money.ts'
import { isOnStorefront } from '../../../core/listings/listing-availability.ts'
import type { ListingStatus } from '../../../core/listings/listing-status.ts'
import type { RemovalKind } from '../../../core/moderation/listing-removal.ts'
import { shopName } from '../../../core/shop/shop-name.ts'

export const REMOVED_FILTERS = ['any', 'removed', 'visible'] as const
export type ListingRemovedFilter = (typeof REMOVED_FILTERS)[number]

export type ListingRowFilters = {
  status?: ListingStatus
  sellerId?: number
  removed?: ListingRemovedFilter
}

export type ListingRow = {
  id: number
  title: string
  sellerId: number
  sellerName: string
  status: ListingStatus
  priceCents: Cents
  quantity: number
  isOnStorefront: boolean
  activeRemoval: { kind: RemovalKind; reason: string } | null
}

/**
 * Every listing across every seller, joined against the single active removal
 * on each. Reads the unlifted `listing_removals` rows once for the whole page
 * rather than per row, so a large table stays one round trip.
 */
export async function listingRows(
  context: Pick<ActionContext, 'db'>,
  filters: ListingRowFilters = {},
): Promise<ListingRow[]> {
  const { db } = context
  let query = db
    .selectFrom('listings')
    .innerJoin('sellers', 'sellers.id', 'listings.sellerId')
    .selectAll('listings')
    .select(['sellers.shopName as sellerShopName', 'sellers.email as sellerEmail'])
    .orderBy('listings.id', 'desc')

  if (filters.status !== undefined) query = query.where('listings.status', '=', filters.status)
  if (filters.sellerId !== undefined) query = query.where('listings.sellerId', '=', filters.sellerId)

  const listings = await query.execute()
  const removals = await activeRemovalsByListing(
    db,
    listings.map((listing) => listing.id),
  )

  return listings
    .map((listing) => toListingRow(listing, removals.get(listing.id) ?? null))
    .filter((row) => matchesRemovedFilter(row, filters.removed ?? 'any'))
}

type ListingJoinRow = {
  id: number
  title: string
  sellerId: number
  sellerShopName: string | null
  sellerEmail: string
  status: ListingStatus
  priceCents: Cents
  quantity: number
}

type ActiveRemoval = { kind: RemovalKind; reason: string }

function toListingRow(listing: ListingJoinRow, activeRemoval: ActiveRemoval | null): ListingRow {
  return {
    id: listing.id,
    title: listing.title,
    sellerId: listing.sellerId,
    sellerName: shopName({ shopName: listing.sellerShopName, email: listing.sellerEmail }),
    status: listing.status,
    priceCents: listing.priceCents,
    quantity: listing.quantity,
    isOnStorefront: isOnStorefront(listing.status, activeRemoval !== null),
    activeRemoval,
  }
}

function matchesRemovedFilter(row: ListingRow, filter: ListingRemovedFilter): boolean {
  if (filter === 'removed') return row.activeRemoval !== null
  if (filter === 'visible') return row.activeRemoval === null
  return true
}

async function activeRemovalsByListing(
  db: ActionContext['db'],
  listingIds: readonly number[],
): Promise<ReadonlyMap<number, ActiveRemoval>> {
  const byListing = new Map<number, ActiveRemoval>()
  if (listingIds.length === 0) return byListing

  const removals = await db
    .selectFrom('listingRemovals')
    .select(['listingId', 'kind', 'reason'])
    .where('listingId', 'in', listingIds)
    .where('liftedAt', 'is', null)
    .execute()

  for (const removal of removals) {
    byListing.set(removal.listingId, { kind: removal.kind, reason: removal.reason })
  }

  return byListing
}

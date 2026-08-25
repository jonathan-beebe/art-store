import type { Expression, ExpressionBuilder, SqlBool } from 'kysely'
import type { ActionContext } from '../../../actions/action-context.ts'
import type { ListingId, SellerId } from '../../../core/ids/entity-ids.ts'
import type { Cents } from '../../../core/money.ts'
import { isOnStorefront } from '../../../core/listings/listing-availability.ts'
import type { ListingStatus } from '../../../core/listings/listing-status.ts'
import type { RemovalKind } from '../../../core/moderation/listing-removal.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'
import { toCount } from '../../../db/count.ts'
import type { Database } from '../../../db/schema.ts'
import { shopName } from '../../../core/shop/shop-name.ts'

export const REMOVED_FILTERS = ['any', 'removed', 'visible'] as const
export type ListingRemovedFilter = (typeof REMOVED_FILTERS)[number]

export type ListingRowFilters = {
  status?: ListingStatus
  sellerId?: SellerId
  removed?: ListingRemovedFilter
}

export type ListingRow = {
  id: ListingId
  title: string
  sellerId: SellerId
  sellerName: string
  status: ListingStatus
  priceCents: Cents
  quantity: number
  isOnStorefront: boolean
  activeRemoval: { kind: RemovalKind; reason: string } | null
}

type ListingsFilter = ExpressionBuilder<Database, 'listings'>

function hasActiveRemoval(eb: ListingsFilter): Expression<SqlBool> {
  return eb.exists(
    eb
      .selectFrom('listingRemovals')
      .select('listingRemovals.id')
      .whereRef('listingRemovals.listingId', '=', 'listings.id')
      .where('listingRemovals.liftedAt', 'is', null),
  )
}

function matchesListingRowFilters(eb: ListingsFilter, filters: ListingRowFilters): Expression<SqlBool>[] {
  const conditions: Expression<SqlBool>[] = []

  if (filters.status !== undefined) conditions.push(eb('listings.status', '=', filters.status))
  if (filters.sellerId !== undefined) conditions.push(eb('listings.sellerId', '=', filters.sellerId))

  const removed = filters.removed ?? 'any'
  if (removed === 'removed') conditions.push(hasActiveRemoval(eb))
  if (removed === 'visible') conditions.push(eb.not(hasActiveRemoval(eb)))

  return conditions
}

/** How many listings match `filters`, independent of which page of them is shown. */
export async function countListingRows(
  context: Pick<ActionContext, 'db'>,
  filters: ListingRowFilters = {},
): Promise<number> {
  const counted = await context.db
    .selectFrom('listings')
    .select(({ fn }) => fn.countAll<string | number | bigint>().as('total'))
    .where((eb) => eb.and(matchesListingRowFilters(eb, filters)))
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}

/**
 * One page of listings across every seller, joined against the single active
 * removal on each. Reads the unlifted `listing_removals` rows once for the
 * page rather than per row, so a large table stays one round trip.
 */
export async function listingRows(
  context: Pick<ActionContext, 'db'>,
  filters: ListingRowFilters = {},
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<ListingRow[]> {
  const { db } = context
  const listings = await db
    .selectFrom('listings')
    .innerJoin('sellers', 'sellers.id', 'listings.sellerId')
    .select([
      'listings.id as id',
      'listings.title as title',
      'listings.sellerId as sellerId',
      'listings.status as status',
      'listings.priceCents as priceCents',
      'listings.quantity as quantity',
      'sellers.shopName as sellerShopName',
      'sellers.email as sellerEmail',
    ])
    .where((eb) => eb.and(matchesListingRowFilters(eb, filters)))
    .orderBy('listings.createdAt', 'desc')
    .orderBy('listings.id', 'desc')
    .offset(page.offset)
    .limit(page.limit)
    .execute()

  const removals = await activeRemovalsByListing(
    db,
    listings.map((listing) => listing.id),
  )

  return listings.map((listing) => toListingRow(listing, removals.get(listing.id) ?? null))
}

type ListingJoinRow = {
  id: ListingId
  title: string
  sellerId: SellerId
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

async function activeRemovalsByListing(
  db: ActionContext['db'],
  listingIds: readonly ListingId[],
): Promise<ReadonlyMap<ListingId, ActiveRemoval>> {
  const byListing = new Map<ListingId, ActiveRemoval>()
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

import type { Expression, ExpressionBuilder, SqlBool } from 'kysely'
import type { ListingId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { toCount } from '../../../db/count.ts'
import type { Database } from '../../../db/schema.ts'
import { BROWSABLE_STATUSES, isPurchasable } from '../../../core/listings/listing-availability.ts'
import type { ListingStatus } from '../../../core/listings/listing-status.ts'
import type { ListingPage } from '../../../core/shop/listing-page.ts'
import { searchLikePattern, type ListingSearch } from '../../../core/shop/listing-search.ts'

/** A listing as the grid draws it: the picture, the price, and whose shop it is. */
export type StorefrontListing = {
  id: ListingId
  title: string
  slug: string
  medium: string | null
  priceCents: number
  imagePath: string | null
  isPurchasable: boolean
  seller: { shopName: string | null; email: string }
}

type ListingFilter = ExpressionBuilder<Database, 'listings'>

/**
 * Browse and search show listings that are for sale and that no admin has
 * removed. A sold listing keeps its own page but leaves the grid.
 */
function isBrowsable(eb: ListingFilter): Expression<SqlBool> {
  return eb.and([
    eb('listings.status', 'in', BROWSABLE_STATUSES),
    // The removal half of `isOnStorefront`, in the only dialect a where clause speaks.
    eb.not(
      eb.exists(
        eb
          .selectFrom('listingRemovals')
          .select('listingRemovals.id')
          .whereRef('listingRemovals.listingId', '=', 'listings.id')
          .where('listingRemovals.liftedAt', 'is', null),
      ),
    ),
  ])
}

function matchesSearch(eb: ListingFilter, search: ListingSearch): Expression<SqlBool>[] {
  const conditions: Expression<SqlBool>[] = []

  if (search.term !== null) {
    const pattern = searchLikePattern(search)
    conditions.push(
      eb.or([
        eb('listings.title', 'like', pattern),
        eb('listings.description', 'like', pattern),
        eb('listings.medium', 'like', pattern),
      ]),
    )
  }

  if (search.medium !== null) conditions.push(eb('listings.medium', '=', search.medium))

  return conditions
}

export async function countStorefrontListings(
  db: AppDatabase,
  search: ListingSearch,
): Promise<number> {
  const counted = await db
    .selectFrom('listings')
    .select(({ fn }) => fn.countAll<string | number | bigint>().as('total'))
    .where((eb) => eb.and([isBrowsable(eb), ...matchesSearch(eb, search)]))
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}

export async function findStorefrontListings(
  db: AppDatabase,
  input: { search: ListingSearch; page: ListingPage },
): Promise<readonly StorefrontListing[]> {
  const rows = await db
    .selectFrom('listings')
    .innerJoin('sellers', 'sellers.id', 'listings.sellerId')
    .select([
      'listings.id as id',
      'listings.title as title',
      'listings.slug as slug',
      'listings.medium as medium',
      'listings.priceCents as priceCents',
      'listings.imagePath as imagePath',
      'listings.status as status',
      'listings.quantity as quantity',
      'sellers.shopName as sellerShopName',
      'sellers.email as sellerEmail',
    ])
    .where((eb) => eb.and([isBrowsable(eb), ...matchesSearch(eb, input.search)]))
    // Newest first: an artist who lists today is on the first screen.
    .orderBy('listings.createdAt', 'desc')
    .orderBy('listings.id', 'desc')
    .offset(input.page.offset)
    .limit(input.page.limit)
    .execute()

  return rows.map(toStorefrontListing)
}

/**
 * Rows selected by a query that has already dropped removed listings, so the
 * availability the card shows turns on status and stock alone.
 */
export function toStorefrontListing(row: {
  id: ListingId
  title: string
  slug: string
  medium: string | null
  priceCents: number
  imagePath: string | null
  status: ListingStatus
  quantity: number
  sellerShopName: string | null
  sellerEmail: string
}): StorefrontListing {
  const { sellerShopName, sellerEmail, status, quantity, ...listing } = row

  return {
    ...listing,
    isPurchasable: isPurchasable(status, quantity, false),
    seller: { shopName: sellerShopName, email: sellerEmail },
  }
}

/** The media on offer right now, which is what the filter can choose between. */
export async function findStorefrontMedia(db: AppDatabase): Promise<readonly string[]> {
  const rows = await db
    .selectFrom('listings')
    .select('listings.medium as medium')
    .distinct()
    .where((eb) => isBrowsable(eb))
    .where('listings.medium', 'is not', null)
    .where('listings.medium', '!=', '')
    .orderBy('listings.medium')
    .execute()

  return rows.flatMap((row) => (row.medium === null ? [] : [row.medium]))
}

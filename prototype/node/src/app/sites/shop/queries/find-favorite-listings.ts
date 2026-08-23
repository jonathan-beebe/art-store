import { STOREFRONT_STATUSES } from '../../../core/listings/listing-availability.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { toStorefrontListing, type StorefrontListing } from './find-storefront-listings.ts'

/**
 * The pieces a customer saved and can still reach: a listing that was archived
 * or removed leaves the page with the rest of the storefront, and a sold one
 * stays, marked sold.
 */
export async function findFavoriteListings(
  db: AppDatabase,
  customerId: number,
): Promise<readonly StorefrontListing[]> {
  const rows = await db
    .selectFrom('favorites')
    .innerJoin('listings', 'listings.id', 'favorites.listingId')
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
    .where('favorites.customerId', '=', customerId)
    .where('listings.status', 'in', STOREFRONT_STATUSES)
    // The removal half of `isOnStorefront`, in the only dialect a where clause speaks.
    .where((eb) =>
      eb.not(
        eb.exists(
          eb
            .selectFrom('listingRemovals')
            .select('listingRemovals.id')
            .whereRef('listingRemovals.listingId', '=', 'listings.id')
            .where('listingRemovals.liftedAt', 'is', null),
        ),
      ),
    )
    .orderBy('favorites.id', 'desc')
    .execute()

  return rows.map(toStorefrontListing)
}

export async function isListingFavorited(
  db: AppDatabase,
  input: { customerId: number; listingId: number },
): Promise<boolean> {
  const saved = await db
    .selectFrom('favorites')
    .select('id')
    .where('customerId', '=', input.customerId)
    .where('listingId', '=', input.listingId)
    .executeTakeFirst()

  return saved !== undefined
}

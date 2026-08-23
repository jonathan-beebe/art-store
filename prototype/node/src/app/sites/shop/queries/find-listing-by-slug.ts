import type { AppDatabase } from '../../../db/database.ts'
import type { Listing } from '../../../db/commerce-schema.ts'

/** A listing with the shop behind it, which is the pair every listing page shows. */
export type ListedArtwork = {
  listing: Listing
  seller: { shopName: string | null; email: string }
}

/**
 * The listing a URL names, whatever its status. Whether a visitor may see it is
 * a core predicate over that status and its removals, so this reads and the
 * route decides.
 */
export async function findListingBySlug(
  db: AppDatabase,
  slug: string,
): Promise<ListedArtwork | null> {
  const row = await db
    .selectFrom('listings')
    .innerJoin('sellers', 'sellers.id', 'listings.sellerId')
    .selectAll('listings')
    .select(['sellers.shopName as sellerShopName', 'sellers.email as sellerEmail'])
    .where('listings.slug', '=', slug)
    .executeTakeFirst()

  if (row === undefined) return null

  const { sellerShopName, sellerEmail, ...listing } = row

  return { listing, seller: { shopName: sellerShopName, email: sellerEmail } }
}

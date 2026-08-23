import { activeListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import { isOnStorefront, isPurchasable } from '../../../core/listings/listing-availability.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { findListingBySlug, type ListedArtwork } from './find-listing-by-slug.ts'

export type StorefrontArtwork = ListedArtwork & {
  /** Whether this piece can still be bought, as against merely looked at. */
  isPurchasable: boolean
}

/**
 * The listing a URL names, when a visitor may see it at all. A draft, an
 * archived piece, and one an admin removed all come back null, so every page
 * over a slug answers them the same way.
 */
export async function findListingOnStorefront(
  db: AppDatabase,
  slug: string,
): Promise<StorefrontArtwork | null> {
  const found = await findListingBySlug(db, slug)
  if (found === null) return null

  const { listing } = found
  const hasActiveRemoval = (await activeListingRemoval({ db }, listing.id)) !== null
  if (!isOnStorefront(listing.status, hasActiveRemoval)) return null

  return {
    ...found,
    isPurchasable: isPurchasable(listing.status, listing.quantity, hasActiveRemoval),
  }
}

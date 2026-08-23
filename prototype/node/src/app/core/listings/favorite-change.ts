import type { ListingEventType } from './listing-event-type.ts'

export const FAVORITE_CHANGES = ['added', 'removed'] as const
export type FavoriteChange = (typeof FAVORITE_CHANGES)[number]

const LISTING_EVENTS: Record<FavoriteChange, ListingEventType> = {
  added: 'favorite',
  removed: 'unfavorite',
}

// One button both favorites and unfavorites, so what it does next follows
// from what the visitor has saved already.
export function favoriteChangeFor(isFavorited: boolean): FavoriteChange {
  return isFavorited ? 'removed' : 'added'
}

export function listingEventForFavoriteChange(change: FavoriteChange): ListingEventType {
  return LISTING_EVENTS[change]
}

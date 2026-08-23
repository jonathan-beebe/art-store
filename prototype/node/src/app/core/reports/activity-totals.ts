import type { ListingEventType } from '../listings/listing-event-type.ts'

/** What a listing's event log adds up to. An unfavorite is its own event and
 * leaves the favorites count where it was. */
export type ActivityTotals = { views: number; favorites: number; cartAdds: number }

export function activityTotals(countsByEventType: Partial<Record<ListingEventType, number>>): ActivityTotals {
  return {
    views: countsByEventType.view ?? 0,
    favorites: countsByEventType.favorite ?? 0,
    cartAdds: countsByEventType.cart_add ?? 0,
  }
}

export function totalActivity(totals: ActivityTotals): number {
  return totals.views + totals.favorites + totals.cartAdds
}

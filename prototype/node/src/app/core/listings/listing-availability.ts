import type { ListingStatus } from './listing-status.ts'

// A sold listing keeps its page so a link a buyer already followed still
// leads somewhere; a draft or archived one was never public.
export const STOREFRONT_STATUSES: readonly ListingStatus[] = ['for_sale', 'sold']

/** What the grid and the search results draw: a sold piece keeps its page and leaves the grid. */
export const BROWSABLE_STATUSES: readonly ListingStatus[] = ['for_sale']

export function isOnStorefront(status: ListingStatus, hasActiveRemoval: boolean): boolean {
  return !hasActiveRemoval && STOREFRONT_STATUSES.includes(status)
}

export function isPurchasable(status: ListingStatus, quantity: number, hasActiveRemoval: boolean): boolean {
  return !hasActiveRemoval && status === 'for_sale' && quantity > 0
}

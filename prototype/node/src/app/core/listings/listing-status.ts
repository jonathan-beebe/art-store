import { TransitionError } from '../transition-error.ts'

export const LISTING_STATUSES = ['draft', 'for_sale', 'sold', 'archived'] as const
export type ListingStatus = (typeof LISTING_STATUSES)[number]

export const LISTING_STATUS_TRANSITIONS: Record<ListingStatus, readonly ListingStatus[]> = {
  draft: ['for_sale', 'archived'],
  for_sale: ['sold', 'archived'],
  // A declined card hands the stock back, so a sold-out listing returns to the storefront.
  sold: ['for_sale'],
  archived: [],
}

export function canTransitionListing(from: ListingStatus, to: ListingStatus): boolean {
  const allowed: readonly ListingStatus[] | undefined = LISTING_STATUS_TRANSITIONS[from]
  return (allowed ?? []).includes(to)
}

export function transitionListing(from: ListingStatus, to: ListingStatus): ListingStatus {
  if (canTransitionListing(from, to)) {
    return to
  }

  throw new TransitionError(`A listing cannot move from ${from} to ${to}.`)
}

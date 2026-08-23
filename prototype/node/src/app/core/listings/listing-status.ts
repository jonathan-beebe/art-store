import { TransitionError } from '../transition-error.ts'

export const LISTING_STATUSES = ['draft', 'for_sale', 'sold', 'archived'] as const
export type ListingStatus = (typeof LISTING_STATUSES)[number]

export const LISTING_STATUS_TRANSITIONS = {
  draft: ['for_sale', 'archived'],
  for_sale: ['sold', 'archived'],
  // A declined card hands the stock back, so a sold-out listing returns to the storefront.
  sold: ['for_sale'],
  archived: [],
} as const satisfies Record<ListingStatus, readonly ListingStatus[]>

export function canTransitionListing(from: ListingStatus, to: ListingStatus): boolean {
  const allowed: readonly ListingStatus[] = LISTING_STATUS_TRANSITIONS[from]

  return allowed.includes(to)
}

export function transitionListing(from: ListingStatus, to: ListingStatus): ListingStatus {
  if (canTransitionListing(from, to)) {
    return to
  }

  throw new TransitionError(`A listing cannot move from ${from} to ${to}.`)
}

// A listing under an active admin removal stays off the storefront whatever its
// own status, so nothing may put it back on sale until the removal is lifted.
export function isBlockedByRemoval(to: ListingStatus, hasActiveRemoval: boolean): boolean {
  return hasActiveRemoval && to === 'for_sale'
}

export function availableListingTransitions(
  status: ListingStatus,
  hasActiveRemoval: boolean,
): readonly ListingStatus[] {
  return LISTING_STATUS_TRANSITIONS[status].filter((next) => !isBlockedByRemoval(next, hasActiveRemoval))
}

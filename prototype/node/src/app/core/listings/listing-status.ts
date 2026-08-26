import { BrokenContractError } from '../defect.ts'
import { refused, type Refusal } from '../refusal.ts'

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

export type ListingTransition =
  | { outcome: 'allowed'; status: ListingStatus }
  | Refusal<'illegal_transition'>

export function transitionListing(from: ListingStatus, to: ListingStatus): ListingTransition {
  if (canTransitionListing(from, to)) {
    return { outcome: 'allowed', status: to }
  }

  return refused('illegal_transition', { status_from: from, status_to: to })
}

/**
 * Unwraps `transitionListing` for a caller inside the application that only
 * ever asks for a move the lifecycle table allows. A refusal reaching here is
 * a broken contract, not a domain outcome to handle.
 */
export function listingMovedTo(from: ListingStatus, to: ListingStatus): ListingStatus {
  const transition = transitionListing(from, to)
  if (transition.outcome === 'allowed') return transition.status

  throw new BrokenContractError(
    transition.reason,
    `A listing cannot move from ${from} to ${to}.`,
    transition.data,
  )
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

import { LISTING_STATUS_TRANSITIONS, type ListingStatus } from '../../core/listings/listing-status.ts'

/**
 * The statuses the seller portal offers next for a listing. An active admin
 * removal takes `for_sale` off the table even where the lifecycle would
 * otherwise allow it, so a removed listing cannot be put back on sale from
 * the seller side.
 */
export function sellerListingTransitions(
  status: ListingStatus,
  hasActiveRemoval: boolean,
): readonly ListingStatus[] {
  const transitions = LISTING_STATUS_TRANSITIONS[status]

  return hasActiveRemoval ? transitions.filter((next) => next !== 'for_sale') : transitions
}

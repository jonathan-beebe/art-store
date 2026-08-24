import type { ListingId } from '../ids/entity-ids.ts'

export type CartLine = { listingId: ListingId; quantity: number }

export type CustomerMergePlan = {
  /** The verified customer's cart after the fold, one line per listing. */
  cartLines: readonly CartLine[]
  /** Anonymous favorites the verified customer does not already have — repoint these rows. */
  favoritesToMove: readonly ListingId[]
  /** Anonymous favorites that duplicate one the verified customer already has — delete these rows. */
  favoritesToDrop: readonly ListingId[]
}

function foldCartLines(
  verifiedLines: readonly CartLine[],
  anonymousLines: readonly CartLine[],
  stockByListing: ReadonlyMap<ListingId, number>,
): CartLine[] {
  const order: ListingId[] = []
  const quantityByListing = new Map<ListingId, number>()

  for (const line of [...verifiedLines, ...anonymousLines]) {
    if (!quantityByListing.has(line.listingId)) {
      order.push(line.listingId)
    }
    quantityByListing.set(line.listingId, (quantityByListing.get(line.listingId) ?? 0) + line.quantity)
  }

  const lines: CartLine[] = []
  for (const listingId of order) {
    const summed = quantityByListing.get(listingId) ?? 0
    const stock = stockByListing.get(listingId)
    const quantity = stock === undefined ? summed : Math.min(summed, Math.max(stock, 0))
    if (quantity > 0) {
      lines.push({ listingId, quantity })
    }
  }
  return lines
}

/**
 * Splits the anonymous customer's favorites into the ones that can move
 * (nothing named that listing yet) and the ones that must be dropped instead
 * (the verified customer already favorited it, so moving the row would
 * duplicate it).
 */
function partitionFavorites(
  verifiedIds: readonly ListingId[],
  anonymousIds: readonly ListingId[],
): Pick<CustomerMergePlan, 'favoritesToMove' | 'favoritesToDrop'> {
  const alreadyFavorited = new Set(verifiedIds)
  const seen = new Set<ListingId>()
  const favoritesToMove: ListingId[] = []
  const favoritesToDrop: ListingId[] = []

  for (const listingId of anonymousIds) {
    if (seen.has(listingId)) continue
    seen.add(listingId)

    if (alreadyFavorited.has(listingId)) {
      favoritesToDrop.push(listingId)
    } else {
      favoritesToMove.push(listingId)
    }
  }

  return { favoritesToMove, favoritesToDrop }
}

export function planCustomerMerge(input: {
  verifiedCartLines: readonly CartLine[]
  anonymousCartLines: readonly CartLine[]
  verifiedFavoriteListingIds: readonly ListingId[]
  anonymousFavoriteListingIds: readonly ListingId[]
  /** Units in stock per listing. A listing absent from this map contributes no cap. */
  stockByListing: ReadonlyMap<ListingId, number>
}): CustomerMergePlan {
  return {
    cartLines: foldCartLines(input.verifiedCartLines, input.anonymousCartLines, input.stockByListing),
    ...partitionFavorites(input.verifiedFavoriteListingIds, input.anonymousFavoriteListingIds),
  }
}

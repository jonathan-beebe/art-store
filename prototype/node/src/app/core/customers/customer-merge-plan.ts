export type CartLine = { listingId: number; quantity: number }

export type CustomerMergePlan = {
  /** The verified customer's cart after the fold, one line per listing. */
  cartLines: readonly CartLine[]
  /** Anonymous favorites the verified customer does not already have — repoint these rows. */
  favoritesToMove: readonly number[]
  /** Anonymous favorites that duplicate one the verified customer already has — delete these rows. */
  favoritesToDrop: readonly number[]
}

function foldCartLines(
  verifiedLines: readonly CartLine[],
  anonymousLines: readonly CartLine[],
  stockByListing: ReadonlyMap<number, number>,
): CartLine[] {
  const order: number[] = []
  const quantityByListing = new Map<number, number>()

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
  verifiedIds: readonly number[],
  anonymousIds: readonly number[],
): Pick<CustomerMergePlan, 'favoritesToMove' | 'favoritesToDrop'> {
  const alreadyFavorited = new Set(verifiedIds)
  const seen = new Set<number>()
  const favoritesToMove: number[] = []
  const favoritesToDrop: number[] = []

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
  verifiedFavoriteListingIds: readonly number[]
  anonymousFavoriteListingIds: readonly number[]
  /** Units in stock per listing. A listing absent from this map contributes no cap. */
  stockByListing: ReadonlyMap<number, number>
}): CustomerMergePlan {
  return {
    cartLines: foldCartLines(input.verifiedCartLines, input.anonymousCartLines, input.stockByListing),
    ...partitionFavorites(input.verifiedFavoriteListingIds, input.anonymousFavoriteListingIds),
  }
}

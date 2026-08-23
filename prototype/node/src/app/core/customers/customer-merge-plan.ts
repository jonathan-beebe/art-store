export type CartLine = { listingId: number; quantity: number }

export type CustomerMergePlan = {
  /** The verified customer's cart after the fold, one line per listing. */
  cartLines: readonly CartLine[]
  /** The verified customer's favorites after the fold, no duplicates. */
  favoriteListingIds: readonly number[]
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

function foldFavoriteIds(
  verifiedIds: readonly number[],
  anonymousIds: readonly number[],
): number[] {
  return [...new Set([...verifiedIds, ...anonymousIds])]
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
    favoriteListingIds: foldFavoriteIds(input.verifiedFavoriteListingIds, input.anonymousFavoriteListingIds),
  }
}

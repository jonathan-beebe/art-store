import { transitionListing, type ListingStatus } from './listing-status.ts'
import type { StockChange } from './stock-change.ts'

export type ListingStock = { quantity: number; status: ListingStatus }

function rejectAnEmptyChange(items: number): void {
  if (items < 1) {
    throw new RangeError(`a stock change covers at least one item, got ${items}`)
  }
}

export function stockAfterSale(input: { quantity: number; status: ListingStatus; sold: number }): ListingStock {
  const { quantity, status, sold } = input
  rejectAnEmptyChange(sold)
  if (status !== 'for_sale') {
    throw new RangeError(`a listing that is ${status} cannot be sold`)
  }
  if (sold > quantity) {
    throw new RangeError(`a listing with ${quantity} left cannot sell ${sold}`)
  }

  const remaining = quantity - sold
  return { quantity: remaining, status: remaining === 0 ? transitionListing(status, 'sold') : status }
}

export function stockAfterRestock(input: { quantity: number; status: ListingStatus; restored: number }): ListingStock {
  const { quantity, status, restored } = input
  rejectAnEmptyChange(restored)

  return {
    quantity: quantity + restored,
    status: status === 'sold' ? transitionListing(status, 'for_sale') : status,
  }
}

export function stockAfter(
  change: StockChange,
  input: { quantity: number; status: ListingStatus; items: number },
): ListingStock {
  const { quantity, status, items } = input
  switch (change) {
    case 'take':
      return stockAfterSale({ quantity, status, sold: items })
    case 'restore':
      return stockAfterRestock({ quantity, status, restored: items })
    case 'keep':
      return { quantity, status }
    default:
      throw new TypeError(`unknown stock change: ${JSON.stringify(change)}`)
  }
}

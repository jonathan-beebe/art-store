import type { Cents } from '../money.ts'
import { addCents, ZERO_CENTS } from '../money.ts'
import { cartLineTotal, type CartLine } from './cart-line.ts'

/** What a cart is worth, whole and split by the seller each line belongs to. */
export type SellerSubtotal = {
  sellerId: number
  subtotalCents: Cents
}

export type CartTotals = {
  itemCount: number
  subtotalCents: Cents
  subtotalsBySeller: readonly SellerSubtotal[]
}

function totalOf(lines: readonly CartLine[]): Cents {
  return lines.reduce((sum, line) => addCents(sum, cartLineTotal(line)), ZERO_CENTS)
}

function subtotalsBySeller(lines: readonly CartLine[]): SellerSubtotal[] {
  const linesBySeller = new Map<number, CartLine[]>()
  for (const line of lines) {
    const existing = linesBySeller.get(line.sellerId) ?? []
    existing.push(line)
    linesBySeller.set(line.sellerId, existing)
  }

  return [...linesBySeller.entries()]
    .sort(([a], [b]) => a - b)
    .map(([sellerId, sellerLines]) => ({ sellerId, subtotalCents: totalOf(sellerLines) }))
}

export function cartTotals(lines: readonly CartLine[]): CartTotals {
  return {
    itemCount: lines.reduce((sum, line) => sum + line.quantity, 0),
    subtotalCents: totalOf(lines),
    subtotalsBySeller: subtotalsBySeller(lines),
  }
}

export function checkoutTotals(lines: readonly CartLine[]): CartTotals {
  if (lines.length === 0) {
    throw new RangeError('an order needs at least one item')
  }
  return cartTotals(lines)
}

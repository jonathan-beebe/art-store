import type { OrderStatus } from './order-status.ts'
import type { StockChange } from '../listings/stock-change.ts'

const RELEASED_BY: readonly OrderStatus[] = ['payment_failed', 'cancelled']

/** The stock an order holds. Placement claims it; a declined card hands it
 * back so the listing returns to the storefront, and a retry claims it again. */
export function holdsStock(status: OrderStatus): boolean {
  return !RELEASED_BY.includes(status)
}

export function stockChangeBetween(input: { from: OrderStatus; to: OrderStatus }): StockChange {
  const { from, to } = input

  if (!holdsStock(from) && holdsStock(to)) {
    return 'take'
  }
  if (holdsStock(from) && !holdsStock(to)) {
    return 'restore'
  }

  return 'keep'
}

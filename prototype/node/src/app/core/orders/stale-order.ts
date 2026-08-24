import type { OrderStatus } from './order-status.ts'

const MILLISECONDS_PER_HOUR = 60 * 60 * 1000

/**
 * The one status the sweep touches. An order that reached
 * `awaiting_payment` has a verified customer behind it and a payment form to
 * come back to, so age alone is not a reason to cancel it.
 */
export const SWEEPABLE_ORDER_STATUS: OrderStatus = 'pending_verification'

/**
 * The instant an unverified order has to have been placed before to count as
 * stale. Nothing placed at or after it is swept, so a run at the same clock
 * twice sweeps the same set.
 */
export function staleOrderCutoff(now: Date, staleHours: number): Date {
  if (!Number.isFinite(staleHours) || staleHours <= 0) {
    throw new RangeError(`a stale-order window is a positive number of hours, got ${staleHours}`)
  }

  return new Date(now.getTime() - staleHours * MILLISECONDS_PER_HOUR)
}

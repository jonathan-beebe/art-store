import { TransitionError } from '../transition-error.ts'
import { hasDeparted, type FulfillmentStatus } from './fulfillment-status.ts'
import { isCardApproved, type CardDecision } from '../payments/card-decision.ts'

export const ORDER_STATUSES = [
  'pending_verification',
  'awaiting_payment',
  'paid',
  'payment_failed',
  'partially_shipped',
  'shipped',
  'delivered',
  'cancelled',
] as const
export type OrderStatus = (typeof ORDER_STATUSES)[number]

export const ORDER_STATUS_TRANSITIONS: Record<OrderStatus, readonly OrderStatus[]> = {
  pending_verification: ['awaiting_payment', 'cancelled'],
  awaiting_payment: ['paid', 'payment_failed', 'cancelled'],
  // A retry that is declined again leaves the order where it already was.
  payment_failed: ['paid', 'payment_failed', 'cancelled'],
  paid: ['partially_shipped', 'shipped'],
  partially_shipped: ['shipped'],
  shipped: ['delivered'],
  delivered: [],
  cancelled: [],
}

const CANCELLABLE_STATUSES: readonly OrderStatus[] = ['pending_verification', 'awaiting_payment', 'payment_failed']

export function canTransitionOrder(from: OrderStatus, to: OrderStatus): boolean {
  const allowed: readonly OrderStatus[] | undefined = ORDER_STATUS_TRANSITIONS[from]
  return (allowed ?? []).includes(to)
}

export function transitionOrder(from: OrderStatus, to: OrderStatus): OrderStatus {
  if (canTransitionOrder(from, to)) {
    return to
  }

  throw new TransitionError(`An order cannot move from ${from} to ${to}.`)
}

export function orderStatusForPlacement(isPurchaserVerified: boolean): OrderStatus {
  return isPurchaserVerified ? 'awaiting_payment' : 'pending_verification'
}

export function orderStatusAfterVerification(status: OrderStatus): OrderStatus {
  return canTransitionOrder(status, 'awaiting_payment') ? 'awaiting_payment' : status
}

export function orderStatusFromCardDecision(decision: CardDecision): OrderStatus {
  return isCardApproved(decision) ? 'paid' : 'payment_failed'
}

/** A multi-seller order rolls up from its fulfillments: a delivered one mixed
 * with an unshipped one is still partially shipped. */
export function orderStatusFromFulfillments(statuses: readonly FulfillmentStatus[]): OrderStatus {
  if (statuses.length === 0) {
    throw new RangeError('an order rolls up from at least one fulfillment')
  }
  if (statuses.every((status) => status === 'delivered')) {
    return 'delivered'
  }
  if (statuses.every((status) => hasDeparted(status))) {
    return 'shipped'
  }
  if (statuses.some((status) => hasDeparted(status))) {
    return 'partially_shipped'
  }

  return 'paid'
}

export function isCancellable(status: OrderStatus): boolean {
  return CANCELLABLE_STATUSES.includes(status)
}

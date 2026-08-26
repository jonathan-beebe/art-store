import { BrokenContractError } from '../defect.ts'
import { refused, type Refusal } from '../refusal.ts'
import { hasDeparted, isReversed, type FulfillmentStatus } from './fulfillment-status.ts'
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
  'refunded',
] as const
export type OrderStatus = (typeof ORDER_STATUSES)[number]

export const ORDER_STATUS_TRANSITIONS = {
  pending_verification: ['awaiting_payment', 'cancelled'],
  awaiting_payment: ['paid', 'payment_failed', 'cancelled'],
  // A retry that is declined again leaves the order where it already was.
  payment_failed: ['paid', 'payment_failed', 'cancelled'],
  paid: ['partially_shipped', 'shipped', 'refunded'],
  partially_shipped: ['shipped', 'refunded'],
  shipped: ['delivered', 'refunded'],
  delivered: ['refunded'],
  cancelled: [],
  refunded: [],
} as const satisfies Record<OrderStatus, readonly OrderStatus[]>

const CANCELLABLE_STATUSES = [
  'pending_verification',
  'awaiting_payment',
  'payment_failed',
] as const satisfies readonly OrderStatus[]

export function canTransitionOrder(from: OrderStatus, to: OrderStatus): boolean {
  const allowed: readonly OrderStatus[] = ORDER_STATUS_TRANSITIONS[from]

  return allowed.includes(to)
}

export type OrderTransition = { outcome: 'allowed'; status: OrderStatus } | Refusal<'illegal_transition'>

export function transitionOrder(from: OrderStatus, to: OrderStatus): OrderTransition {
  if (canTransitionOrder(from, to)) {
    return { outcome: 'allowed', status: to }
  }

  return refused('illegal_transition', { status_from: from, status_to: to })
}

/**
 * Unwraps `transitionOrder` for a caller inside the application that only
 * ever asks for a move the lifecycle table allows. A refusal reaching here is
 * a broken contract, not a domain outcome to handle.
 */
export function orderMovedTo(from: OrderStatus, to: OrderStatus): OrderStatus {
  const transition = transitionOrder(from, to)
  if (transition.outcome === 'allowed') return transition.status

  throw new BrokenContractError(
    transition.reason,
    `An order cannot move from ${from} to ${to}.`,
    transition.data,
  )
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

/**
 * A multi-seller order rolls up from its fulfillments: a delivered one mixed
 * with an unshipped one is still partially shipped. Declined and refunded
 * halves are money the customer has back, so they drop out of the roll-up —
 * one shipped beside one declined reads as shipped, and an order with nothing
 * left is refunded.
 */
export function orderStatusFromFulfillments(statuses: readonly FulfillmentStatus[]): OrderStatus {
  if (statuses.length === 0) {
    throw new RangeError('an order rolls up from at least one fulfillment')
  }

  const live = statuses.filter((status) => !isReversed(status))
  if (live.length === 0) {
    return 'refunded'
  }
  if (live.every((status) => status === 'delivered')) {
    return 'delivered'
  }
  if (live.every((status) => hasDeparted(status))) {
    return 'shipped'
  }
  if (live.some((status) => hasDeparted(status))) {
    return 'partially_shipped'
  }

  return 'paid'
}

export function isCancellable(status: OrderStatus): boolean {
  const cancellable: readonly OrderStatus[] = CANCELLABLE_STATUSES

  return cancellable.includes(status)
}

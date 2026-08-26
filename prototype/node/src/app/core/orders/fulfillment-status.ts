import { BrokenContractError } from '../defect.ts'
import { refused, transitionFacts, type Refusal } from '../refusal.ts'

export const FULFILLMENT_STATUSES = [
  'awaiting_shipment',
  'shipped',
  'delivered',
  'declined',
  'refunded',
] as const
export type FulfillmentStatus = (typeof FULFILLMENT_STATUSES)[number]

export const FULFILLMENT_STATUS_TRANSITIONS = {
  awaiting_shipment: ['shipped', 'declined', 'refunded'],
  shipped: ['delivered', 'refunded'],
  delivered: ['refunded'],
  declined: [],
  refunded: [],
} as const satisfies Record<FulfillmentStatus, readonly FulfillmentStatus[]>

const DEPARTED = ['shipped', 'delivered'] as const satisfies readonly FulfillmentStatus[]

/** The two endings that hand the money back rather than earn it. */
export const REVERSED_FULFILLMENT_STATUSES = ['declined', 'refunded'] as const satisfies readonly FulfillmentStatus[]

export function canTransitionFulfillment(from: FulfillmentStatus, to: FulfillmentStatus): boolean {
  const allowed: readonly FulfillmentStatus[] = FULFILLMENT_STATUS_TRANSITIONS[from]

  return allowed.includes(to)
}

export type FulfillmentTransition =
  | { outcome: 'allowed'; status: FulfillmentStatus }
  | Refusal<'illegal_transition'>

export function transitionFulfillment(from: FulfillmentStatus, to: FulfillmentStatus): FulfillmentTransition {
  if (canTransitionFulfillment(from, to)) {
    return { outcome: 'allowed', status: to }
  }

  return refused('illegal_transition', { status_from: from, status_to: to })
}

/**
 * Unwraps `transitionFulfillment` for a caller inside the application that
 * only ever asks for a move the lifecycle table allows. A refusal reaching
 * here is a broken contract, not a domain outcome to handle.
 */
export function fulfillmentMovedTo(from: FulfillmentStatus, to: FulfillmentStatus): FulfillmentStatus {
  const transition = transitionFulfillment(from, to)
  if (transition.outcome === 'allowed') return transition.status

  throw new BrokenContractError(transition.reason, fulfillmentTransitionRefusalCopy(transition), transition.data)
}

/** The sentence a refused fulfillment move shows, worded from the refusal's
 * own data rather than a status the caller read before the write. */
export function fulfillmentTransitionRefusalCopy(refusal: Refusal): string {
  const { status_from, status_to } = transitionFacts(refusal)

  return `A fulfillment cannot move from ${status_from} to ${status_to}.`
}

export function hasDeparted(status: FulfillmentStatus): boolean {
  const departed: readonly FulfillmentStatus[] = DEPARTED

  return departed.includes(status)
}

/**
 * Declined or refunded: the customer has the money back, so the order stops
 * counting this half of it and the platform forgoes the fee on it.
 */
export function isReversed(status: FulfillmentStatus): boolean {
  const reversed: readonly FulfillmentStatus[] = REVERSED_FULFILLMENT_STATUSES

  return reversed.includes(status)
}

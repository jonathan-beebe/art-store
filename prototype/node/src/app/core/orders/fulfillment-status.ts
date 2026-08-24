import { TransitionError } from '../transition-error.ts'

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
const REVERSED = ['declined', 'refunded'] as const satisfies readonly FulfillmentStatus[]

export function canTransitionFulfillment(from: FulfillmentStatus, to: FulfillmentStatus): boolean {
  const allowed: readonly FulfillmentStatus[] = FULFILLMENT_STATUS_TRANSITIONS[from]

  return allowed.includes(to)
}

export function transitionFulfillment(from: FulfillmentStatus, to: FulfillmentStatus): FulfillmentStatus {
  if (canTransitionFulfillment(from, to)) {
    return to
  }

  throw new TransitionError(`A fulfillment cannot move from ${from} to ${to}.`)
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
  const reversed: readonly FulfillmentStatus[] = REVERSED

  return reversed.includes(status)
}

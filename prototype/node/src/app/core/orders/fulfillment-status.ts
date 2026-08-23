import { TransitionError } from '../transition-error.ts'

export const FULFILLMENT_STATUSES = ['awaiting_shipment', 'shipped', 'delivered'] as const
export type FulfillmentStatus = (typeof FULFILLMENT_STATUSES)[number]

export const FULFILLMENT_STATUS_TRANSITIONS: Record<FulfillmentStatus, readonly FulfillmentStatus[]> = {
  awaiting_shipment: ['shipped'],
  shipped: ['delivered'],
  delivered: [],
}

const DEPARTED: readonly FulfillmentStatus[] = ['shipped', 'delivered']

export function canTransitionFulfillment(from: FulfillmentStatus, to: FulfillmentStatus): boolean {
  const allowed: readonly FulfillmentStatus[] | undefined = FULFILLMENT_STATUS_TRANSITIONS[from]
  return (allowed ?? []).includes(to)
}

export function transitionFulfillment(from: FulfillmentStatus, to: FulfillmentStatus): FulfillmentStatus {
  if (canTransitionFulfillment(from, to)) {
    return to
  }

  throw new TransitionError(`A fulfillment cannot move from ${from} to ${to}.`)
}

export function hasDeparted(status: FulfillmentStatus): boolean {
  return DEPARTED.includes(status)
}

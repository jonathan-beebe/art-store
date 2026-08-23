import { canTransitionOrder, type OrderStatus } from './order-status.ts'

/** An order awaits a card for as long as one could still carry it to paid,
 * which is what the storefront asks before it shows a card form. */
export function awaitsCard(status: OrderStatus): boolean {
  return canTransitionOrder(status, 'paid')
}

/** A guest's order is unpaid before it is chargeable: verifying the address
 * is the step between. */
export function isUnpaid(status: OrderStatus): boolean {
  return awaitsCard(status) || canTransitionOrder(status, 'awaiting_payment')
}

export function isPayable(status: OrderStatus, isPurchaserVerified: boolean): boolean {
  return isPurchaserVerified && awaitsCard(status)
}

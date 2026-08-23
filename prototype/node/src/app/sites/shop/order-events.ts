import type { FastifyRequest } from 'fastify'
import type { Order } from '../../db/commerce-schema.ts'

/** The order exists and holds the stock it claimed. */
export function logOrderPlaced(request: FastifyRequest, order: Order): void {
  request.log.info(
    {
      event: 'order.placed',
      orderId: order.id,
      customerId: order.customerId,
      amountCents: order.totalCents,
    },
    'order placed',
  )
}

/** The card was charged one way or the other by the time this runs, so the
 * event names what happened rather than what was attempted. */
export function logChargeOutcome(request: FastifyRequest, charged: Order): void {
  const paid = charged.status === 'paid'

  request.log.info(
    {
      event: paid ? 'order.paid' : 'order.declined',
      orderId: charged.id,
      amountCents: charged.totalCents,
    },
    paid ? 'order paid' : 'card declined',
  )
}

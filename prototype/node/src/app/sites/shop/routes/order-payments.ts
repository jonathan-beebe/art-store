import type { FastifyPluginCallback, FastifyRequest } from 'fastify'
import { z } from 'zod'
import type { ActionContext } from '../../../actions/action-context.ts'
import { finalizeOrder } from '../../../actions/orders/finalize-order.ts'
import { markAwaitingPayment } from '../../../actions/orders/mark-awaiting-payment.ts'
import { runInTransaction } from '../../../actions/transaction.ts'
import { awaitsCard, isUnpaid } from '../../../core/orders/order-payment.ts'
import type { Order } from '../../../db/commerce-schema.ts'
import { requireVerifiedCustomer } from '../../../plugins/identity.ts'
import { customerOrderPath, loadCustomerOrder } from '../customer-order.ts'
import { declineNotice } from '../decline-notice.ts'
import { refuseBlockedCustomer } from '../refuse-blocked-customer.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'

const form = z.object({ card_number: z.string().optional() })

/**
 * Verifies the order and charges it in one transaction, so the status
 * `awaitsCard` branches on is the one `finalizeOrder` acts on. Null once
 * verification lands the order somewhere a card can no longer pay it —
 * already paid, or moved by a concurrent request.
 */
async function chargeVerifiedOrder(
  context: ActionContext,
  input: { orderId: number; cardNumber: string },
): Promise<Order | null> {
  return runInTransaction(context, async (transacted) => {
    const order = await markAwaitingPayment(transacted, input.orderId)
    if (!awaitsCard(order.status)) return null

    return finalizeOrder(transacted, { orderId: order.id, cardNumber: input.cardNumber })
  })
}

/** The card was charged one way or the other by the time this runs, so the
 * event names what happened rather than what was attempted. */
function logChargeOutcome(request: FastifyRequest, charged: Order): void {
  const paid = charged.status === 'paid'

  request.log.info(
    { event: paid ? 'order.paid' : 'order.declined', orderId: charged.id, amountCents: charged.totalCents },
    paid ? 'order paid' : 'card declined',
  )
}

/**
 * The card, once the address behind the order is verified. Verification is what
 * carries a guest's order out of `pending_verification`, so both routes sit
 * behind the sign-in guard and call `markAwaitingPayment` before they charge.
 */
export const orderPaymentRoutes: FastifyPluginCallback = (shop, _options, done) => {
  const { db, clock } = shop

  shop.get('/orders/:id/pay', { preHandler: requireVerifiedCustomer }, async (request, reply) => {
    const found = await loadCustomerOrder(shop, request)
    if (found === null) return renderNotFound(reply)
    if (!isUnpaid(found.order.status)) return await reply.redirect(`/orders/${found.order.id}`)

    const order = await markAwaitingPayment({ db, clock }, found.order.id)

    return reply.render(
      'pay',
      shopPage({
        title: `Pay for order #${order.id}`,
        order,
        declineMessage: declineNotice(found.lastPayment),
      }),
    )
  })

  shop.post(
    '/orders/:id/pay',
    { preHandler: [requireVerifiedCustomer, refuseBlockedCustomer(customerOrderPath)] },
    async (request, reply) => {
      const found = await loadCustomerOrder(shop, request)
      if (found === null) return renderNotFound(reply)

      const submitted = form.safeParse(request.body).data ?? {}
      const charged = await chargeVerifiedOrder(
        { db, clock },
        { orderId: found.order.id, cardNumber: submitted.card_number ?? '' },
      )
      if (charged === null) return renderNotFound(reply)

      logChargeOutcome(request, charged)

      return await reply.redirect(`/orders/${charged.id}`)
    },
  )

  done()
}

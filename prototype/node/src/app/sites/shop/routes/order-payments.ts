import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { finalizeOrder } from '../../../actions/orders/finalize-order.ts'
import { markAwaitingPayment } from '../../../actions/orders/mark-awaiting-payment.ts'
import { awaitsCard, isUnpaid } from '../../../core/orders/order-payment.ts'
import { requireVerifiedCustomer } from '../../../plugins/identity.ts'
import { customerOrderPath, loadCustomerOrder } from '../customer-order.ts'
import { declineNotice } from '../decline-notice.ts'
import { refuseBlockedCustomer } from '../refuse-blocked-customer.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'

const form = z.object({ card_number: z.string().optional() })

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

      const order = await markAwaitingPayment({ db, clock }, found.order.id)
      if (!awaitsCard(order.status)) return renderNotFound(reply)

      const submitted = form.safeParse(request.body).data ?? {}
      await finalizeOrder({ db, clock }, { orderId: order.id, cardNumber: submitted.card_number ?? '' })

      return await reply.redirect(`/orders/${order.id}`)
    },
  )

  done()
}

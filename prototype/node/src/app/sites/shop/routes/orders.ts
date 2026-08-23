import type { FastifyPluginCallback } from 'fastify'
import { cancelOrder } from '../../../actions/orders/cancel-order.ts'
import { isCancellable } from '../../../core/orders/order-status.ts'
import { isPayable, isUnpaid } from '../../../core/orders/order-payment.ts'
import { signedInActorId } from '../../../plugins/identity.ts'
import { declineNotice } from '../decline-notice.ts'
import { loadCustomerOrder } from '../customer-order.ts'
import { findCustomerOrders } from '../queries/find-customer-orders.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

export const orderRoutes: FastifyPluginCallback = (shop, _options, done) => {
  shop.get('/orders', async (request, reply) => {
    const orders = await findCustomerOrders(shop.db, storefrontCustomer(request).id)

    return reply.render('orders', shopPage({ title: 'Orders', orders }))
  })

  shop.get('/orders/:id', async (request, reply) => {
    const found = await loadCustomerOrder(shop, request)
    if (found === null) return renderNotFound(reply)

    const { order, fulfillments, lastPayment } = found

    return reply.render(
      'order',
      shopPage({
        title: `Order #${order.id}`,
        order,
        fulfillments,
        declineMessage: declineNotice(lastPayment),
        isUnpaid: isUnpaid(order.status),
        isPayable: isPayable(order.status, signedInActorId(request, 'customer') !== null),
        isCancellable: isCancellable(order.status),
      }),
    )
  })

  shop.post('/orders/:id/cancel', async (request, reply) => {
    const found = await loadCustomerOrder(shop, request)
    // Fast 404 for a status cancelOrder would refuse anyway — transitionOrder is the authority.
    if (found === null || !isCancellable(found.order.status)) return renderNotFound(reply)

    await cancelOrder({ db: shop.db, clock: shop.clock }, found.order.id)
    reply.setFlash({ notice: 'Order cancelled. Anything it held is back on the storefront.' })

    return await reply.redirect(`/orders/${found.order.id}`)
  })

  done()
}

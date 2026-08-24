import { cancelOrder } from '../../../actions/orders/cancel-order.ts'
import { isCancellable } from '../../../core/orders/order-status.ts'
import { idParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestActions } from '../../../http/request-actions.ts'
import { loadCustomerOrder } from '../customer-order.ts'
import { findCustomerOrders } from '../queries/find-customer-orders.ts'
import { renderOrderPage } from '../order-page.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

export const orderRoutes: ZodRoutes = (shop, _options, done) => {
  shop.get('/orders', async (request, reply) => {
    const orders = await findCustomerOrders(shop.db, storefrontCustomer(request).id)

    return reply.render('orders', shopPage({ title: 'Orders', orders }))
  })

  shop.get('/orders/:id', { schema: { params: idParams('ord') } }, async (request, reply) => {
    return await renderOrderPage(shop, request, reply, request.params.id)
  })

  shop.post('/orders/:id/cancel', { schema: { params: idParams('ord') } }, async (request, reply) => {
    const found = await loadCustomerOrder(shop, request, request.params.id)
    // Fast 404 for a status cancelOrder would refuse anyway — transitionOrder is the authority.
    if (found === null || !isCancellable(found.order.status)) return renderNotFound(reply)

    await cancelOrder(requestActions(request), found.order.id)
    reply.setFlash({ notice: 'Order cancelled. Anything it held is back on the storefront.' })

    return await reply.redirect(`/orders/${found.order.id}`)
  })

  done()
}

import { z } from 'zod'
import { ORDER_STATUSES } from '../../../core/orders/order-status.ts'
import { idParams, idValue, optionalFilter } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { orderDetail } from '../queries/order-detail.ts'
import { orderRows } from '../queries/order-rows.ts'

const ordersQuery = z.object({
  status: optionalFilter(z.enum(ORDER_STATUSES)),
  customer: optionalFilter(idValue('cus')),
})

export const orderRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/orders', { schema: { querystring: ordersQuery } }, async (request, reply) => {
    const { status, customer } = request.query
    const orders = await orderRows({ db: admin.db }, { status, customerId: customer })

    return reply.render(
      'orders',
      adminPage('Orders', {
        orders,
        statuses: ORDER_STATUSES,
        filters: { status: status ?? '', customer: customer ?? '' },
      }),
    )
  })

  admin.get('/orders/:id', { schema: { params: idParams('ord') } }, async (request, reply) => {
    const detail = await orderDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render('order', adminPage(`Order ${detail.order.id}`, detail))
  })

  done()
}

import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { ORDER_STATUSES } from '../../../core/orders/order-status.ts'
import { adminPage } from '../page.ts'
import { orderRows } from '../queries/order-rows.ts'

const ordersQuery = z.object({
  status: z.enum(ORDER_STATUSES).optional(),
  customer: z.coerce.number().int().positive().optional(),
})

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const query = ordersQuery.parse(request.query)
  const orders = await orderRows(
    { db: request.server.db },
    { status: query.status, customerId: query.customer },
  )

  return reply.render(
    'orders',
    adminPage('Orders', {
      orders,
      statuses: ORDER_STATUSES,
      filters: { status: query.status ?? '', customer: query.customer ?? '' },
    }),
  )
}

export const orderRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/orders', index)

  done()
}

import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { FULFILLMENT_STATUSES } from '../../../core/orders/fulfillment-status.ts'
import { adminPage } from '../page.ts'
import { fulfillmentRows } from '../queries/fulfillment-rows.ts'

const fulfillmentsQuery = z.object({
  status: z.enum(FULFILLMENT_STATUSES).optional(),
  seller: z.coerce.number().int().positive().optional(),
})

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const query = fulfillmentsQuery.parse(request.query)
  const fulfillments = await fulfillmentRows(
    { db: request.server.db },
    { status: query.status, sellerId: query.seller },
  )

  return reply.render(
    'fulfillments',
    adminPage('Fulfillments', {
      fulfillments,
      statuses: FULFILLMENT_STATUSES,
      filters: { status: query.status ?? '', seller: query.seller ?? '' },
    }),
  )
}

export const fulfillmentRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/fulfillments', index)

  done()
}

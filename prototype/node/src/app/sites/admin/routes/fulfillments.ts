import { z } from 'zod'
import { FULFILLMENT_STATUSES } from '../../../core/orders/fulfillment-status.ts'
import { idParams, idValue, optionalFilter } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { fulfillmentDetail } from '../queries/fulfillment-detail.ts'
import { fulfillmentRows } from '../queries/fulfillment-rows.ts'

const fulfillmentsQuery = z.object({
  status: optionalFilter(z.enum(FULFILLMENT_STATUSES)),
  seller: optionalFilter(idValue('sel')),
})

export const fulfillmentRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get(
    '/fulfillments',
    { schema: { querystring: fulfillmentsQuery } },
    async (request, reply) => {
      const { status, seller } = request.query
      const fulfillments = await fulfillmentRows({ db: admin.db }, { status, sellerId: seller })

      return reply.render(
        'fulfillments',
        adminPage('Fulfillments', {
          fulfillments,
          statuses: FULFILLMENT_STATUSES,
          filters: { status: status ?? '', seller: seller ?? '' },
        }),
      )
    },
  )

  admin.get('/fulfillments/:id', { schema: { params: idParams('ful') } }, async (request, reply) => {
    const detail = await fulfillmentDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render('fulfillment', adminPage(`Fulfillment ${detail.fulfillment.id}`, detail))
  })

  done()
}

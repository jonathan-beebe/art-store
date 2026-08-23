import { z } from 'zod'
import { customerName } from '../../../core/messaging/participant-name.ts'
import { idParams, optionalFilter } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { customerDetail } from '../queries/customer-detail.ts'
import { CUSTOMER_STANDING_FILTERS, customerRows } from '../queries/customer-rows.ts'

const customersQuery = z.object({
  standing: optionalFilter(z.enum(CUSTOMER_STANDING_FILTERS)).default('all'),
})

export const customerRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/customers', { schema: { querystring: customersQuery } }, async (request, reply) => {
    const { standing } = request.query
    const rows = await customerRows({ db: admin.db }, standing)

    return reply.render('customers', adminPage('Customers', { rows, standing }))
  })

  admin.get('/customers/:id', { schema: { params: idParams } }, async (request, reply) => {
    const detail = await customerDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render('customer', adminPage(customerName(detail.customer), detail))
  })

  done()
}

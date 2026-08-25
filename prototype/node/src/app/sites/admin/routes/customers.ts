import { z } from 'zod'
import { customerName } from '../../../core/messaging/participant-name.ts'
import { listPage } from '../../../core/paging/list-page.ts'
import { idParams, optionalFilter } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { customerDetail } from '../queries/customer-detail.ts'
import { CUSTOMER_STANDING_FILTERS, countCustomerRows, customerRows } from '../queries/customer-rows.ts'

// One screen of the customers table.
const ROWS_PER_PAGE = 25

const customersQuery = z.object({
  standing: optionalFilter(z.enum(CUSTOMER_STANDING_FILTERS)).default('all'),
  page: z.string().optional(),
})

export const customerRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/customers', { schema: { querystring: customersQuery } }, async (request, reply) => {
    const { standing } = request.query
    const page = listPage({
      requested: request.query.page,
      size: ROWS_PER_PAGE,
      totalCount: await countCustomerRows({ db: admin.db }, standing),
    })
    const rows = await customerRows({ db: admin.db }, standing, page)

    return reply.render(
      'customers',
      adminPage('Customers', { rows, standing, page, filterQuery: new URLSearchParams({ standing }).toString() }),
    )
  })

  admin.get('/customers/:id', { schema: { params: idParams('cus') } }, async (request, reply) => {
    const detail = await customerDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render('customer', adminPage(customerName(detail.customer), detail))
  })

  done()
}

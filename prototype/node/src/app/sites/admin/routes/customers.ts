import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { adminPage } from '../page.ts'
import { customerDetail } from '../queries/customer-detail.ts'
import { CUSTOMER_STANDING_FILTERS, customerRows } from '../queries/customer-rows.ts'

const idParams = z.object({ id: z.coerce.number().int().positive() })
const standingQuery = z.object({ standing: z.enum(CUSTOMER_STANDING_FILTERS).default('all') })

function parseCustomerId(params: unknown): number | null {
  const parsed = idParams.safeParse(params)

  return parsed.success ? parsed.data.id : null
}

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const parsed = standingQuery.safeParse(request.query)
  const standing = parsed.success ? parsed.data.standing : 'all'

  const rows = await customerRows({ db: request.server.db }, standing)

  return reply.render('customers', adminPage('Customers', { rows, standing }))
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply | void> {
  const id = parseCustomerId(request.params)
  if (id === null) return reply.callNotFound()

  const detail = await customerDetail({ db: request.server.db }, id)
  if (detail === null) return reply.callNotFound()

  const title = detail.customer.email ?? `Customer ${detail.customer.id}`

  return reply.render('customer', adminPage(title, detail))
}

export const customerRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/customers', index)
  admin.get('/customers/:id', show)

  done()
}

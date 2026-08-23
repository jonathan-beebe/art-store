import type { FastifyInstance, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { findCustomerOrder, type CustomerOrder } from './queries/find-customer-order.ts'
import { storefrontCustomer } from './storefront-customer.ts'

const parameters = z.object({ id: z.coerce.number().int().positive() })

function orderIdFrom(request: FastifyRequest): number | null {
  const asked = parameters.safeParse(request.params)

  return asked.success ? asked.data.id : null
}

/** Where a page under `/orders/:id` sends a visitor it will not serve. */
export function customerOrderPath(request: FastifyRequest): string {
  const id = orderIdFrom(request)

  return id === null ? '/orders' : `/orders/${id}`
}

/**
 * The order the URL names, read as the customer behind the request. Null covers
 * an id that names nothing and an id that names someone else's order alike, so
 * every page over it answers the same way.
 */
export async function loadCustomerOrder(
  app: FastifyInstance,
  request: FastifyRequest,
): Promise<CustomerOrder | null> {
  const orderId = orderIdFrom(request)
  if (orderId === null) return null

  return findCustomerOrder(app.db, { orderId, customerId: storefrontCustomer(request).id })
}

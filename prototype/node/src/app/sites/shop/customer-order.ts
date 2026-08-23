import type { FastifyInstance, FastifyRequest } from 'fastify'
import { parseIdParam } from '../../plugins/id-param.ts'
import { findCustomerOrder, type CustomerOrder } from './queries/find-customer-order.ts'
import { storefrontCustomer } from './storefront-customer.ts'

/** Where a page under `/orders/:id` sends a visitor it will not serve. */
export function customerOrderPath(request: FastifyRequest): string {
  const id = parseIdParam(request.params)

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
  const orderId = parseIdParam(request.params)
  if (orderId === null) return null

  return findCustomerOrder(app.db, { orderId, customerId: storefrontCustomer(request).id })
}

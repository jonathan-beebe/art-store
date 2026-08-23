import type { FastifyRequest } from 'fastify'
import type { Selectable } from 'kysely'
import type { CustomerTable } from '../../db/schema.ts'

/**
 * The customer every storefront page hangs its cart, favorites, and orders on.
 * `resolveCustomerIdentity` puts one on every request under the storefront
 * plugin, so an absent one is a route registered in the wrong place.
 */
export function storefrontCustomer(request: FastifyRequest): Selectable<CustomerTable> {
  const customer = request.currentCustomer

  if (customer === null) {
    throw new Error('a storefront route runs behind resolveCustomerIdentity')
  }

  return customer
}

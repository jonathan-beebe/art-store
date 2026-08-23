import type { FastifyRequest } from 'fastify'
import { signedInActorId } from '../../plugins/identity.ts'
import { findCustomerNotifications } from './queries/find-customer-notifications.ts'
import { shopPage } from './shop-page.ts'

export async function customerAccountView(
  request: FastifyRequest,
): Promise<Record<string, unknown>> {
  const customerId = signedInActorId(request, 'customer')
  if (customerId === null) {
    throw new Error('customerAccountView runs behind requireVerifiedCustomer')
  }

  return shopPage({ notifications: await findCustomerNotifications(request.server.db, customerId) })
}

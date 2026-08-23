import type { FastifyRequest, preHandlerAsyncHookHandler } from 'fastify'
import { currentCustomerStanding } from '../../actions/moderation/current-customer-standing.ts'
import { canShop } from '../../core/moderation/customer-standing.ts'
import { blockedShopperNotice } from '../../core/shop/blocked-shopper-notice.ts'

/**
 * Keeps a blocked customer off every path that buys something, and says why.
 * The destination is per route so the visitor lands back where they were
 * rather than on a page that means nothing to them.
 */
export function refuseBlockedCustomer(
  destination: (request: FastifyRequest) => string,
): preHandlerAsyncHookHandler {
  return async (request, reply) => {
    const customer = request.currentCustomer
    if (customer === null) return undefined

    const standing = await currentCustomerStanding({ db: request.server.db }, customer.id)
    if (canShop(standing)) return undefined

    reply.setFlash({ alert: blockedShopperNotice(standing) })

    return await reply.redirect(destination(request))
  }
}

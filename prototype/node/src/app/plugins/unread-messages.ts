import type { FastifyInstance, FastifyRequest, preHandlerHookHandler } from 'fastify'
import { unreadMessageCount } from '../actions/messaging/conversation-inbox.ts'
import { resolveCustomerFromCookie } from '../actions/customers/resolve-customer-from-cookie.ts'
import type { ActorType } from '../core/auth/actor-type.ts'
import { identityId } from './identity.ts'

declare module 'fastify' {
  interface FastifyRequest {
    unreadMessageCount: number
  }
}

/**
 * Every layout carries a messages link with what is waiting behind it, so the
 * count has to be on the request before any page renders.
 */
export function addUnreadMessages(app: FastifyInstance): void {
  app.decorateRequest('unreadMessageCount', 0)
}

const ACTOR_IDS = {
  seller: async (request) => request.currentSeller?.id ?? null,
  admin: async (request) => request.currentAdmin?.id ?? null,
  // The storefront's sign-in pages sit outside the hook that resolves a
  // customer, so the cookie is read here rather than a count going missing on
  // the account page.
  customer: async (request) => {
    if (request.currentCustomer !== null) return request.currentCustomer.id

    const customer = await resolveCustomerFromCookie(
      { db: request.server.db },
      identityId(request, 'customer'),
    )

    return customer?.id ?? null
  },
} satisfies Record<ActorType, (request: FastifyRequest) => Promise<number | null>>

/** Counts what this site's actor has waiting, for the nav link in its layout. */
export function countUnreadMessages(actorType: ActorType): preHandlerHookHandler {
  return async (request) => {
    const actorId = await ACTOR_IDS[actorType](request)
    if (actorId === null) return

    request.unreadMessageCount = await unreadMessageCount(
      { db: request.server.db },
      { type: actorType, id: actorId },
    )
  }
}

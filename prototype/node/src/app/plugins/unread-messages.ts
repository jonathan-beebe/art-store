import type { FastifyRequest, preHandlerAsyncHookHandler } from 'fastify'
import type { MessagingActor } from '../actions/messaging/conversation-actor.ts'
import { unreadMessageCount } from '../actions/messaging/conversation-inbox.ts'
import type { ActorType } from '../core/auth/actor-type.ts'
import { rootPlugin } from './root-plugin.ts'

declare module 'fastify' {
  interface FastifyRequest {
    unreadMessageCount: number
  }
}

/**
 * Every layout carries a messages link with what is waiting behind it, so the
 * count has to be on the request before any page renders.
 */
export const unreadMessages = rootPlugin({ name: 'unreadMessages' }, (app) => {
  app.decorateRequest('unreadMessageCount', 0)
})

const SITE_ACTORS = {
  seller: async ({ currentSeller }) =>
    currentSeller === null ? null : { type: 'seller', id: currentSeller.id },
  admin: async ({ currentAdmin }) =>
    currentAdmin === null ? null : { type: 'admin', id: currentAdmin.id },
  // Every site this hook runs on registers an identity hook first, so the
  // actor is already on the request (see resolveSellerIdentity and
  // resolveAdminIdentity in identity.ts, and resolveCustomerIdentity in
  // storefront.ts).
  customer: async ({ currentCustomer }) =>
    currentCustomer === null ? null : { type: 'customer', id: currentCustomer.id },
} satisfies Record<ActorType, (request: FastifyRequest) => Promise<MessagingActor | null>>

/** Counts what this site's actor has waiting, for the nav link in its layout. */
export function countUnreadMessages(actorType: ActorType): preHandlerAsyncHookHandler {
  return async (request) => {
    const actor = await SITE_ACTORS[actorType](request)
    if (actor === null) return

    request.unreadMessageCount = await unreadMessageCount({ db: request.server.db }, actor)
  }
}

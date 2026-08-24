import type { FastifyRequest } from 'fastify'
import type { ActionContext } from '../actions/action-context.ts'

/**
 * The world an action gets when a request is what asked for it: the database,
 * the clock, and the request's own child logger — so every line the action
 * writes carries the request, session, and actor the request already named.
 */
export function requestActions(request: FastifyRequest): ActionContext {
  return { db: request.server.db, clock: request.server.clock, log: request.log }
}

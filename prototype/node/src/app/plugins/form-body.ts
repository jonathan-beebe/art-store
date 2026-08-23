import type { FastifyRequest } from 'fastify'

/**
 * A submitted form as an object. Fastify leaves `request.body` undefined when a
 * request carries no body at all, and every form schema here already answers
 * for a field that was not filled in, so an absent body reads as an empty form.
 */
export function formBody(request: FastifyRequest): unknown {
  return request.body ?? {}
}

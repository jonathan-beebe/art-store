import type { FastifyReply } from 'fastify'

/**
 * What a route answers for an id that names nothing this seller owns — a
 * listing or fulfillment another seller holds is not found, not refused. It is
 * the portal's own 404 page, the same one a mistyped url reaches.
 */
export function sellerNotFound(reply: FastifyReply): FastifyReply {
  reply.callNotFound()

  return reply
}

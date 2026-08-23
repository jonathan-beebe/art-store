import type { FastifyReply } from 'fastify'

/** What a route answers for an id that names nothing this seller owns — a
 * listing or fulfillment another seller holds is not found, not refused. */
export function sellerNotFound(reply: FastifyReply): FastifyReply {
  return reply.code(404).type('text/plain').send('Not found')
}

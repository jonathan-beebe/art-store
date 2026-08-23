import type { FastifyRequest } from 'fastify'

/** The id of the signed-in seller. Every route in this site sits behind
 * `requireSeller`, so a null `currentSeller` here means that guard did not run. */
export function currentSellerId(request: FastifyRequest): number {
  const seller = request.currentSeller
  if (seller === null) {
    throw new Error('currentSellerId called on a request requireSeller did not guard')
  }

  return seller.id
}

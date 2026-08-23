import type { FastifyRequest } from 'fastify'

/**
 * The origin a link back into this app is built from. `PUBLIC_URL` wins when a
 * deployment states one, because `request.host` is the client's own `Host`
 * header and a magic link is read in a mail client that never saw the request.
 * With no `PUBLIC_URL` the link carries the origin the visitor is already
 * using, which is what lets a clone run with no configuration.
 */
export function requestOrigin(request: FastifyRequest): string {
  return request.server.config.publicUrl ?? `${request.protocol}://${request.host}`
}

export function magicLinkUrl(request: FastifyRequest, token: string): string {
  return `${requestOrigin(request)}/auth/magic/${token}`
}

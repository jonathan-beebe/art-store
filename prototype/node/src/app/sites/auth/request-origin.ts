import type { FastifyRequest } from 'fastify'

/**
 * The scheme and host this request arrived on. A magic link is clicked from a
 * mail client with no idea where the app is served, so the link carries the
 * origin the visitor was already using.
 */
export function requestOrigin(request: FastifyRequest): string {
  return `${request.protocol}://${request.host}`
}

export function magicLinkUrl(request: FastifyRequest, token: string): string {
  return `${requestOrigin(request)}/auth/magic/${token}`
}

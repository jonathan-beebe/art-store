import type { FastifyInstance, FastifyReply, RouteHandlerMethod } from 'fastify'
import { ZodError } from 'zod'
import { rootPlugin } from './root-plugin.ts'
import type { SitePageRenderer } from './site-render.ts'

/** The lowest status that blames the request, and the lowest that blames us. */
export const BAD_REQUEST = 400
const SERVER_FAILURE = 500

const NOT_FOUND = 404

/** What an error page says. Nothing here comes from the error itself. */
export type ErrorPageView = { title: string; message: string }

const CLIENT_FAILURE_PAGE: ErrorPageView = {
  title: 'That request did not work',
  message:
    'Something in the form or the address was not what this page expects. Go back and try it again.',
}

const SERVER_FAILURE_PAGE: ErrorPageView = {
  title: 'Something went wrong',
  message: 'The fault is on this side and it has been recorded. Try again in a moment.',
}

/**
 * Which status answers a failure at the boundary. A rejected parse and a body
 * Fastify itself refused are both the request's fault; everything else is
 * ours, whatever status the error claims.
 */
export function failureStatusCode(error: unknown): number {
  if (error instanceof ZodError) return BAD_REQUEST

  const declared = declaredStatusCode(error)
  const isClientFailure = declared !== null && declared >= BAD_REQUEST && declared < SERVER_FAILURE

  return isClientFailure ? declared : SERVER_FAILURE
}

export function errorPageView(statusCode: number): ErrorPageView {
  return statusCode < SERVER_FAILURE ? CLIENT_FAILURE_PAGE : SERVER_FAILURE_PAGE
}

/** The error page in the layout of whichever site the reply belongs to. */
export function renderErrorPage(reply: FastifyReply, statusCode: number): FastifyReply {
  return reply.code(statusCode).render('error', errorPageView(statusCode))
}

/**
 * Gives one site the page a visitor lands on for a url it does not serve.
 * Fastify keeps a not-found handler inside the context that set it, so each
 * site answers a miss under its own prefix in its own layout — and
 * `reply.callNotFound()` from a route reaches the same page.
 *
 * The renderer is used in place of `reply.render` because `callNotFound` keeps
 * the reply the caller already holds: a miss handed over by the static file
 * routes arrives on a reply that never entered a site.
 */
export function addNotFoundPage(site: FastifyInstance, renderPage: SitePageRenderer): void {
  const answerNotFound: RouteHandlerMethod = async (_request, reply) =>
    renderPage(reply.code(NOT_FOUND), 'not-found', { title: 'Not found' })

  site.setNotFoundHandler(answerNotFound)

  // The static file routes claim `/*` at the root, so without this a url under
  // a portal's prefix is a missing file rather than a page the portal does not
  // serve, and answers in the storefront's layout.
  if (site.prefix !== '') site.all('/*', answerNotFound)
}

/**
 * The one handler every unhandled error reaches. A server fault is logged with
 * the request id the child logger carries and answered with a page that says
 * nothing about it; a bad request is answered as a bad request rather than
 * reported as a crash.
 */
export const errorPages = rootPlugin({ name: 'errorPages' }, (app) => {
  app.setErrorHandler(async (error, request, reply) => {
    const statusCode = failureStatusCode(error)

    if (statusCode >= SERVER_FAILURE) request.log.error({ err: error }, 'the request failed')

    if (rendersPages(reply)) return renderErrorPage(reply, statusCode)

    return reply
      .code(statusCode)
      .type('text/plain; charset=utf-8')
      .send(errorPageView(statusCode).title)
  })
})

function declaredStatusCode(error: unknown): number | null {
  if (typeof error !== 'object' || error === null || !('statusCode' in error)) return null

  return typeof error.statusCode === 'number' ? error.statusCode : null
}

/**
 * `render` is decorated inside each site, so a reply that carries one landed
 * on a site and can answer in that site's layout. `/health` and the magic-link
 * route sit at the root with no layout to reach for.
 */
function rendersPages(reply: FastifyReply): boolean {
  return typeof reply.render === 'function'
}

import type { FastifyRequest } from 'fastify'
import { csrfToken, isValidCsrfToken, submittedCsrfToken } from '../core/security/csrf-token.ts'
import { answerForbidden } from './error-pages.ts'
import { rootPlugin } from './root-plugin.ts'

/** The methods a body can change state under. Every other method skips the
 * guard: it reads nothing state-changing and carries no token to check. */
const STATE_CHANGING_METHODS: ReadonlySet<string> = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

/**
 * State-changing routes the guard leaves unchecked, keyed by `"METHOD url"`
 * against the route's own registered pattern. Empty today: every
 * state-changing route this app registers is a plain HTML form submission,
 * and `csrf.test.ts`'s completeness test reads the app's own route table to
 * hold that so. The shape stays rather than folding into the hook itself, so
 * a route that genuinely cannot carry a token — a webhook, an API a browser
 * never posts to directly — has somewhere to be named, with why, instead of a
 * special case bolted onto the check.
 */
const CSRF_EXEMPT: ReadonlySet<string> = new Set()

function routeKey(method: string, url: string): string {
  return `${method} ${url}`
}

/** Whether the guard leaves this method and registered route pattern alone. */
export function isCsrfExempt(method: string, url: string): boolean {
  return CSRF_EXEMPT.has(routeKey(method, url))
}

/**
 * Verifies the double-submit token on every state-changing request across all
 * three sites from one place, ahead of each route's own schema. `preValidation`
 * runs before Fastify's own schema validation, which is what matters here:
 * `submittedForm` strips a field a route's schema does not declare, so a
 * field forgotten there would already be gone by `preHandler` — checking any
 * later than `preValidation` would silently let such a request through. A
 * missing, foreign, or stale token answers the requesting site's own 403 page.
 */
export const csrfProtection = rootPlugin(
  { name: 'csrfProtection', dependencies: ['requestLog'] },
  (app) => {
    app.addHook('preValidation', async (request, reply) => {
      if (!STATE_CHANGING_METHODS.has(request.method)) return undefined
      if (isCsrfExempt(request.method, request.routeOptions.url ?? '')) return undefined

      const { sessionId } = request
      const submitted = submittedCsrfToken(request.body)

      const verified =
        sessionId !== null &&
        submitted !== null &&
        isValidCsrfToken(submitted, sessionId, app.config.cookieSecret)

      return verified ? undefined : answerForbidden(reply)
    })
  },
)

/**
 * The token this request's browser should submit on its next state-changing
 * request — what a layout hands to its hidden field, the way it already hands
 * `flash` and `identity`. `sessionId` is only ever null before the request-log
 * hook has run, which is before any page renders.
 */
export function csrfTokenForRequest(request: FastifyRequest): string {
  return csrfToken(request.sessionId ?? '', request.server.config.cookieSecret)
}

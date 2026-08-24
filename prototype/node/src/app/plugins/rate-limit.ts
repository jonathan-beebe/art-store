import type { FastifyReply, FastifyRequest } from 'fastify'
import { checkRateLimit } from '../actions/rate-limit/check-rate-limit.ts'
import { actionDid } from '../actions/action-story.ts'
import { normalizeEmail } from '../core/auth/email-address.ts'
import { redactedRateLimitKey } from '../core/rate-limit/redacted-key.ts'
import type { RateLimitName } from '../core/rate-limit/rate-limit-name.ts'
import type { RateLimitDecision } from '../core/rate-limit/rate-limit-window.ts'
import { tooManyRequestsMessage } from '../core/rate-limit/too-many-requests.ts'
import { requestActions } from '../http/request-actions.ts'
import { logLine } from '../log-story.ts'

const TOO_MANY_REQUESTS = 429

/**
 * The client's address for rate limiting. `request.ip` is Fastify's own
 * `trustProxy` computing it: the socket in development, or the first hop
 * past `TRUSTED_PROXIES` once an operator names one — never a header a caller
 * can forge by sending its own `X-Forwarded-For`.
 */
export function clientIp(request: FastifyRequest): string {
  return request.ip
}

const TOO_MANY_REQUESTS_TITLE = 'Too many requests'

/**
 * Whether `reply` belongs to a site that decorated it with `render` —
 * `plugins/site-render.ts` does this inside each site, never at the root, so
 * a route registered at the root (the magic-link verification GET, `/health`)
 * carries no layout to answer a 429 page in.
 */
function rendersPages(reply: FastifyReply): boolean {
  return typeof reply.render === 'function'
}

/**
 * Answers a tripped limit: `Retry-After`, one `rate_limit.exceed` line at
 * `warn`, and the site's own 429 page — the same `error` template a 400 or
 * 500 renders, in the layout the request already landed in, or plain text for
 * a route with no site layout to reach for. A tripped `magic_link_request`
 * also gets a `magic_link.request` `refused` line: the guard runs as a
 * `preHandler`, before `sendMagicLink` would have opened its own `will`, so
 * this is the only place that limit's refusal is told. Returns whether it
 * answered, so a `preHandler` or a route handler falls through to its own
 * next step when it did not.
 *
 * Returns a plain `boolean` rather than the `FastifyReply` it wrote to on
 * purpose: `FastifyReply` implements `then`, so returning one from an `async`
 * function has JavaScript treat it as a thenable and assimilate it — the
 * caller's `await` would resolve to whatever `reply.then` resolves with
 * (`undefined`), not the reply itself, and a caller checking that value for
 * "did this trip" would never see a trip at all.
 */
export async function answerIfRateLimited(
  request: FastifyRequest,
  reply: FastifyReply,
  name: RateLimitName,
  key: string,
  decision: RateLimitDecision,
): Promise<boolean> {
  if (!decision.tripped) return false

  reply.header('Retry-After', String(decision.retryAfterSeconds))

  actionDid(
    requestActions(request),
    'rate_limit.exceed',
    `blocked a request over the ${name} limit`,
    { limit: name, key: redactedRateLimitKey(key), retry_after_seconds: decision.retryAfterSeconds },
    'warn',
  )

  if (name === 'magic_link_request') {
    logLine(request.log, 'info', 'magic_link.request', 'refused', {
      msg: 'refused to send a sign-in link over the rate limit',
      data: { reason: 'rate_limited', key: redactedRateLimitKey(key) },
    })
  }

  const message = tooManyRequestsMessage(decision.retryAfterSeconds)

  if (rendersPages(reply)) {
    reply.code(TOO_MANY_REQUESTS).render('error', { title: TOO_MANY_REQUESTS_TITLE, message })
  } else {
    reply.code(TOO_MANY_REQUESTS).type('text/plain; charset=utf-8').send(message)
  }

  return true
}

/** A `preHandler` that reads the same parsed params its route's handler
 * does — the shape `refuseBlockedCustomer` (`sites/shop/refuse-blocked-customer.ts`)
 * uses for the same reason: `payment_attempt` is keyed by the order id the
 * url names, which only exists once the route's own `params` schema ran. */
type RateLimitKeyRequest<Params> = FastifyRequest & { params: Params }

export type RateLimitGuardOptions<Params> = {
  name: RateLimitName
  /** What the counter is keyed by for this request — an email address, a
   * client ip, or an actor id, per `docs/alignment.md` §3's table. */
  key: (request: RateLimitKeyRequest<Params>) => string
}

/** One limit's `preHandler`, applied to the route(s) `docs/alignment.md` §3
 * names as its guard. Fastify reads an unhandled `preHandler` as "continue"
 * whatever it resolves to, so returning nothing on a miss and the reply on a
 * trip is `answerIfRateLimited`'s boolean turned into the two shapes Fastify
 * itself expects here. */
export function rateLimitGuard<Params = unknown>(
  options: RateLimitGuardOptions<Params>,
): (request: RateLimitKeyRequest<Params>, reply: FastifyReply) => Promise<FastifyReply | undefined> {
  return async (request, reply) => {
    const { config, db, clock } = request.server
    const limit = config.rateLimits[options.name]
    const key = options.key(request)

    const decision = await checkRateLimit({ db, clock }, limit, { name: options.name, key })
    const tripped = await answerIfRateLimited(request, reply, options.name, key, decision)

    return tripped ? reply : undefined
  }
}

/**
 * `magic_link_request` is the one limit §3 keys by two counters at once: the
 * lowercased address and, separately, the client ip. Either can trip it, so
 * the address is checked first and the ip only when the address is still
 * inside its own limit — both counters still move on every call, only the
 * decision short-circuits.
 */
export async function magicLinkRequestDecision(
  request: FastifyRequest,
  email: string,
): Promise<{ decision: RateLimitDecision; key: string }> {
  const { config, db, clock } = request.server
  const limit = config.rateLimits.magic_link_request

  const emailKey = `email:${normalizeEmail(email)}`
  const byEmail = await checkRateLimit({ db, clock }, limit, { name: 'magic_link_request', key: emailKey })
  if (byEmail.tripped) return { decision: byEmail, key: emailKey }

  const ipKey = `ip:${clientIp(request)}`
  const byIp = await checkRateLimit({ db, clock }, limit, { name: 'magic_link_request', key: ipKey })

  return { decision: byIp, key: ipKey }
}

/** The `magic_link_request` `preHandler`, for a route that always sends a
 * link when it runs — sign-in's `POST /login` on every site. `Body` is the
 * route's own parsed body type, the way `rateLimitGuard`'s `Params` is. */
export function magicLinkRequestGuard<Body>(
  email: (request: FastifyRequest & { body: Body }) => string,
): (request: FastifyRequest & { body: Body }, reply: FastifyReply) => Promise<FastifyReply | undefined> {
  return async (request, reply) => {
    const { decision, key } = await magicLinkRequestDecision(request, email(request))
    const tripped = await answerIfRateLimited(request, reply, 'magic_link_request', key, decision)

    return tripped ? reply : undefined
  }
}

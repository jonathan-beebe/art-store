import type { FastifyReply, FastifyRequest } from 'fastify'
import type { SessionId } from '../core/ids/entity-ids.ts'
import { parsePrefixedId } from '../core/ids/prefixed-id.ts'
import { loggablePath, pathnameOf } from '../core/logging/loggable-path.ts'
import { describeError } from '../core/logging/logged-error.ts'
import { requestActor } from '../core/logging/request-actor.ts'
import { isAssetPath } from '../http/asset-manifest.ts'
import { newId } from '../ids.ts'
import { logLine, type LogData } from '../log-story.ts'
import { prefixedMsg } from '../core/logging/story-emoji.ts'
import { identityId } from './identity.ts'
import { rootPlugin } from './root-plugin.ts'

/** Names the browser, not the account: sign-in and sign-out leave it alone. */
const SESSION_COOKIE = 'sid'

// Long enough that a returning visitor's lines still join up with the ones from
// the last time they were here.
const SESSION_LIFETIME_SECONDS = 365 * 24 * 60 * 60

const REQUEST_ID_HEADER = 'X-Request-Id'

declare module 'fastify' {
  interface FastifyRequest {
    /** The `sid` cookie's value, minted on the first response a browser gets.
     * Null only before the request-log hook has run. */
    sessionId: SessionId | null
    /** Set once this request's story has closed with `did`, `failed`, or an abort. */
    storyClosed: boolean
    /** `performance.now()` when the story opened; the abort closer has no `reply`
     * to read `elapsedTime` from, so it measures duration against this instead. */
    storyStartedAt: number
    /** The status code last handed to the client, captured before a streamed
     * response's body goes out; the abort closer reports this since a response
     * that never reached `onResponse` has no final `reply.statusCode` to read. */
    sentStatus: number
  }
}

/**
 * The `http.request` story, and the three fields every other line of a request
 * inherits from it. `request_id` is Fastify's own, labelled to match
 * `docs/alignment.md` §2.1; `session_id` and the actor are bound onto the
 * request's child logger here, so an action logging from four calls deep says
 * who asked for it without being handed anything.
 *
 * Registered before the site plugins, because a route inherits the root's
 * hooks as they stand when its own context is built. `@fastify/static`'s
 * routes are root-context routes regardless of registration order, so the
 * asset check inside each hook — not where this plugin sits — is what keeps
 * an asset request out of the story.
 */
export const requestLog = rootPlugin(
  { name: 'requestLog', dependencies: ['@fastify/cookie'] },
  (app) => {
    app.decorateRequest('sessionId', null)
    app.decorateRequest('storyClosed', false)
    app.decorateRequest('storyStartedAt', 0)
    app.decorateRequest('sentStatus', 0)

    app.addHook('onRequest', async (request, reply) => {
      if (isAssetPath(pathnameOf(request.url))) return

      const sessionId = rememberSession(request, reply)
      request.sessionId = sessionId
      request.storyStartedAt = performance.now()
      request.log = request.log.child(requestBindings(request, sessionId))
      reply.header(REQUEST_ID_HEADER, request.id)

      const path = loggedPath(request)

      logLine(
        request.log,
        'info',
        'http.request',
        'will',
        {
          msg: `${request.method} ${path}`,
          data: { method: request.method, path },
        },
        undefined,
        true,
      )
    })

    app.addHook('onSend', async (request, reply, payload) => {
      if (!isAssetPath(pathnameOf(request.url))) request.sentStatus = reply.statusCode
      return payload
    })

    app.addHook('onResponse', async (request, reply) => {
      if (request.storyClosed || isAssetPath(pathnameOf(request.url))) return
      request.storyClosed = true

      logLine(
        request.log,
        'info',
        'http.request',
        'did',
        {
          msg: `${request.method} ${loggedPath(request)} ${reply.statusCode}`,
          data: { status: reply.statusCode },
        },
        Math.round(reply.elapsedTime),
      )
    })

    app.addHook('onRequestAbort', async (request) => {
      if (request.storyClosed || isAssetPath(pathnameOf(request.url))) return
      request.storyClosed = true

      logLine(
        request.log,
        'info',
        'http.request',
        'did',
        {
          msg: `${request.method} ${loggedPath(request)} ${request.sentStatus}`,
          data: { status: request.sentStatus, disconnected: true },
        },
        Math.round(performance.now() - request.storyStartedAt),
      )
    })
  },
)

/**
 * Closes a request's story with `failed` in place of the `did` the response
 * would otherwise log. Called by the error handler, which is the only place
 * that sees an exception no route caught.
 */
export function logRequestFailure(
  request: FastifyRequest,
  reply: FastifyReply,
  error: unknown,
  statusCode: number,
): void {
  if (request.storyClosed) return
  request.storyClosed = true

  request.log.error(
    {
      event: 'http.request',
      phase: 'failed',
      duration_ms: Math.round(reply.elapsedTime),
      error: describeError(error),
      data: { status: statusCode },
    },
    prefixedMsg(`${request.method} ${loggedPath(request)} ${statusCode}`, 'failed', 'error', true),
  )
}

/** The url, or the route's pattern where a segment of that url is a secret. */
function loggedPath(request: FastifyRequest): string {
  return loggablePath(request.url, request.routeOptions.url)
}

/**
 * The `sid` the browser presented, or a fresh one written back to it. The value
 * is a correlation id with no authority of its own, so it is stored as it
 * reads — an unsigned `ses_…` — and all three prototypes can be compared on it.
 */
function rememberSession(request: FastifyRequest, reply: FastifyReply): SessionId {
  const held = parsePrefixedId('ses', request.cookies[SESSION_COOKIE] ?? '')
  if (held.outcome === 'id') return held.id

  const minted = newId('ses', request.server.clock.now())

  reply.setCookie(SESSION_COOKIE, minted, {
    path: '/',
    httpOnly: true,
    sameSite: 'lax',
    maxAge: SESSION_LIFETIME_SECONDS,
    secure: request.server.config.secureCookies,
  })

  return minted
}

/** Who and which browser, for every line the rest of the request writes. */
function requestBindings(request: FastifyRequest, sessionId: SessionId): LogData {
  const actor = requestActor(pathnameOf(request.url), {
    seller: identityId(request, 'seller'),
    customer: identityId(request, 'customer'),
    admin: identityId(request, 'admin'),
  })

  return {
    session_id: sessionId,
    ...(actor === null ? {} : { actor_type: actor.actorType, actor_id: actor.actorId }),
  }
}

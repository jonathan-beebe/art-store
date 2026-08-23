import { randomUUID } from 'node:crypto'
import type { FastifyRequest, FastifyServerOptions } from 'fastify'
import pino from 'pino'
import type { AppConfig } from './config.ts'

const REQUEST_ID_HEADER = 'x-request-id'

/** Cookies a request log line never shows in cleartext: the three identity
 * cookies, and flash, which carries the debug magic link. */
const REDACTED_COOKIES: ReadonlySet<string> = new Set([
  'seller_id',
  'customer_id',
  'admin_id',
  'flash',
])

const REDACTED_COOKIE_VALUE = '[redacted]'

/**
 * Parses a `Cookie` header into name/value pairs, replacing the value of
 * every cookie in `REDACTED_COOKIES`. `undefined` in, `undefined` out, so a
 * serialized line omits the field for a request that carried no cookies
 * rather than showing it empty.
 */
export function redactedCookies(header: string | undefined): Record<string, string> | undefined {
  if (header === undefined) return undefined

  const cookies: Record<string, string> = {}

  for (const pair of header.split(';')) {
    const separator = pair.indexOf('=')
    if (separator === -1) continue

    const name = pair.slice(0, separator).trim()
    if (name === '') continue

    cookies[name] = REDACTED_COOKIES.has(name)
      ? REDACTED_COOKIE_VALUE
      : pair.slice(separator + 1).trim()
  }

  return cookies
}

/**
 * Fastify's own request fields, plus the cookies that rode in, redacted. An
 * operator can see which side of the marketplace a request carried identity
 * for without a signed session token or a flashed sign-in link ever landing
 * in the log stream.
 */
function serializeRequest(request: FastifyRequest): Record<string, unknown> {
  return {
    method: request.method,
    url: request.url,
    host: request.host,
    remoteAddress: request.ip,
    remotePort: request.socket?.remotePort,
    cookies: redactedCookies(request.headers.cookie),
  }
}

/** The caller's own correlation id, or a fresh one when none arrived. */
export function readOrGenerateRequestId(header: string | string[] | undefined): string {
  const value = Array.isArray(header) ? header[0] : header

  return value === undefined || value.length === 0 ? randomUUID() : value
}

type LoggingServerOptions = Pick<
  FastifyServerOptions,
  'logger' | 'genReqId' | 'requestIdHeader' | 'requestIdLogLabel'
>

/**
 * The logging half of Fastify's server options: every child-logger line
 * carries the caller's `x-request-id` (falling back to a generated one) as
 * `requestId`, and the request line's cookies are redacted. `stream` lets a
 * test capture what was logged; the running app leaves it unset, so pino
 * writes to stdout.
 */
export function loggingOptions(
  config: Pick<AppConfig, 'logLevel'>,
  { stream }: { stream?: pino.DestinationStream } = {},
): LoggingServerOptions {
  return {
    logger: {
      level: config.logLevel,
      serializers: { req: serializeRequest },
      stream,
    },
    genReqId: (rawRequest) => readOrGenerateRequestId(rawRequest.headers[REQUEST_ID_HEADER]),
    requestIdHeader: REQUEST_ID_HEADER,
    requestIdLogLabel: 'requestId',
  }
}

/**
 * A pino instance for a CLI entrypoint, built from the same `LOG_LEVEL` the
 * server reads. `stream` lets a test capture what was logged.
 */
export function createCliLogger(
  config: Pick<AppConfig, 'logLevel'>,
  { stream }: { stream?: pino.DestinationStream } = {},
): pino.Logger {
  return stream === undefined ? pino({ level: config.logLevel }) : pino({ level: config.logLevel }, stream)
}

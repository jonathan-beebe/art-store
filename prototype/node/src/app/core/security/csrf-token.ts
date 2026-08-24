import { createHmac, timingSafeEqual } from 'node:crypto'

/** The form field and the double-submit cookie both name it by this key. */
export const CSRF_FIELD_NAME = '_csrf_token'

/**
 * The double-submit token for one browser. There is no session store to hold
 * a token of its own, so it is derived instead: an HMAC of the browser's
 * `sid` cookie under the app's cookie secret. A page renders it as a hidden
 * field, and a same-origin POST from that browser carries the `sid` cookie
 * back on its own — a cross-site form cannot read that cookie to compute a
 * matching value, and neither can it guess one, since the secret never leaves
 * the server.
 */
export function csrfToken(sessionId: string, secret: string): string {
  return createHmac('sha256', secret).update(sessionId).digest('hex')
}

/**
 * Whether a submitted token is the one `sessionId` and `secret` derive,
 * compared in constant time so a byte-by-byte guess learns nothing from how
 * long a wrong guess took to fail.
 */
export function isValidCsrfToken(submitted: string, sessionId: string, secret: string): boolean {
  const expected = Buffer.from(csrfToken(sessionId, secret))
  const given = Buffer.from(submitted)

  return expected.length === given.length && timingSafeEqual(expected, given)
}

/**
 * The submitted token field, read from a body already parsed by either of the
 * app's two body parsers: `@fastify/formbody` puts a plain string there,
 * `@fastify/multipart` under `attachFieldsToBody` puts a `{ value }` part.
 * Null for a body carrying neither shape — an absent field, one parser wraps
 * every field it did not recognize this way, and a request whose body was
 * never a plain object at all.
 */
export function submittedCsrfToken(body: unknown): string | null {
  if (typeof body !== 'object' || body === null) return null

  const field = (body as Record<string, unknown>)[CSRF_FIELD_NAME]

  if (typeof field === 'string') return field

  if (typeof field === 'object' && field !== null && 'value' in field && typeof field.value === 'string') {
    return field.value
  }

  return null
}

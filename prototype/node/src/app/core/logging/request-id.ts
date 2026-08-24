/**
 * A caller may name its own correlation id with `X-Request-Id`, and that name
 * is echoed back on the response and written into every line of the request.
 * The header is untrusted text going straight into a log stream and a response
 * header, so only a short, plain token is taken; anything else is dropped and
 * the app names the request itself.
 *
 * `docs/alignment.md` §2.1 fixes the pattern, so a caller that threads an id
 * through all three prototypes gets the same answer from each.
 */

const ACCEPTABLE_REQUEST_ID = /^[A-Za-z0-9_-]{1,64}$/

/** Whether a caller-supplied correlation id may be used as it stands. */
export function isAcceptableRequestId(value: string): boolean {
  return ACCEPTABLE_REQUEST_ID.test(value)
}

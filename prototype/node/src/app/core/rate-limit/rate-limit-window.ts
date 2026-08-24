/**
 * The fixed-window decision `docs/alignment.md` §3 describes: a counter keyed
 * by `(name, key, window_start)`, incremented once per request in the shell,
 * and decided here from the count it came back with.
 */
export type RateLimitDecision = { tripped: false } | { tripped: true; retryAfterSeconds: number }

const MILLISECONDS_PER_SECOND = 1000

/** The start of the window `now` falls in, for a window `windowSeconds` long. */
export function windowStart(now: Date, windowSeconds: number): Date {
  const epochSeconds = Math.floor(now.getTime() / MILLISECONDS_PER_SECOND)
  const bucketStartSeconds = epochSeconds - (epochSeconds % windowSeconds)

  return new Date(bucketStartSeconds * MILLISECONDS_PER_SECOND)
}

/**
 * Whether the count a counter came back with — after the increment the
 * request being decided already made — trips the limit, and if so, how long
 * until the window it is filed under ends. Never less than one second, so a
 * `Retry-After` header is never a lie a request sent in the same tick.
 */
export function decideRateLimit(
  count: number,
  limitCount: number,
  start: Date,
  windowSeconds: number,
  now: Date,
): RateLimitDecision {
  if (count <= limitCount) return { tripped: false }

  const windowEndsAt = start.getTime() + windowSeconds * MILLISECONDS_PER_SECOND
  const retryAfterSeconds = Math.max(1, Math.ceil((windowEndsAt - now.getTime()) / MILLISECONDS_PER_SECOND))

  return { tripped: true, retryAfterSeconds }
}

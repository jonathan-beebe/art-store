import type { RateLimit } from './rate-limit-value.ts'

const MILLISECONDS_PER_SECOND = 1000

/**
 * The point before which a `rate_limit_windows` row can never be read by any
 * of `limits` again, and so is safe to delete. It is `asOf` minus the largest
 * `windowSeconds` among the limits that are not `'off'`.
 *
 * A consult at some `now >= asOf` reads `windowStart(now, w)`, which is always
 * `> now - w`, and `now - w >= asOf - maxWindow`. So a row with
 * `window_start < asOf - maxWindow` sits before every window any configured
 * limit could still be reading.
 *
 * Returns `null` when every limit is `'off'` or none are configured — nothing
 * is safely prunable, since a redeploy could re-enable a limit mid-window and
 * a deleted row would silently forgive whatever count it held.
 */
export function expiredWindowCutoff(asOf: Date, limits: readonly RateLimit[]): Date | null {
  const windowSeconds = limits
    .filter((limit): limit is Exclude<RateLimit, 'off'> => limit !== 'off')
    .map((limit) => limit.windowSeconds)

  if (windowSeconds.length === 0) return null

  const maxWindowSeconds = Math.max(...windowSeconds)

  return new Date(asOf.getTime() - maxWindowSeconds * MILLISECONDS_PER_SECOND)
}

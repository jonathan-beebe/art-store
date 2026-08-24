/**
 * The value one limit's environment variable carries, `docs/alignment.md` §3:
 * `<count>/<window>` where window is `<n>s`, `<n>m`, or `<n>h`, or the literal
 * `off`. Parsed once at boot — `config.ts` refuses to boot on a malformed
 * value, so a limit's guard only ever sees an already-valid `RateLimit`.
 */
export type RateLimit = 'off' | { count: number; windowSeconds: number }

export type ParseRateLimitResult =
  | { ok: true; value: RateLimit }
  | { ok: false; error: string }

const RATE_LIMIT_PATTERN = /^(\d+)\/(\d+)(s|m|h)$/

const WINDOW_UNIT_SECONDS = { s: 1, m: 60, h: 3600 } as const

type WindowUnit = keyof typeof WINDOW_UNIT_SECONDS

function isWindowUnit(value: string | undefined): value is WindowUnit {
  return value === 's' || value === 'm' || value === 'h'
}

function isPositiveWholeNumber(value: number): boolean {
  return Number.isSafeInteger(value) && value >= 1
}

/** The three groups `RATE_LIMIT_PATTERN` captures, already matched — parsing
 * them into a `RateLimit` is the part with more than one way to fail. */
function rateLimitFromMatch(value: string, match: RegExpExecArray): ParseRateLimitResult {
  const [, countText = '', windowText = '', unit] = match
  const count = Number(countText)
  const windowValue = Number(windowText)

  if (!isPositiveWholeNumber(count)) {
    return { ok: false, error: `"${value}" has a count that is not a positive whole number` }
  }

  if (!isPositiveWholeNumber(windowValue)) {
    return { ok: false, error: `"${value}" has a window that is not a positive whole number` }
  }

  // The pattern's third group only ever matches one of these three letters,
  // so a match this far already guarantees it — the guard is here to carry
  // that into the type `WINDOW_UNIT_SECONDS` is indexed by, not to catch a
  // shape `RATE_LIMIT_PATTERN` would let through.
  if (!isWindowUnit(unit)) {
    return { ok: false, error: `"${value}" has a window unit that is not "s", "m", or "h"` }
  }

  return { ok: true, value: { count, windowSeconds: windowValue * WINDOW_UNIT_SECONDS[unit] } }
}

/**
 * Parses one limit's environment value, or `defaultValue` when the variable
 * was unset. Never throws: a malformed value comes back as the `ok: false`
 * arm, which the caller turns into a boot refusal.
 */
export function parseRateLimit(
  raw: string | undefined,
  defaultValue: string,
): ParseRateLimitResult {
  const value = raw ?? defaultValue

  if (value === 'off') return { ok: true, value: 'off' }

  const match = RATE_LIMIT_PATTERN.exec(value)
  if (match === null) {
    return {
      ok: false,
      error: `"${value}" is not a rate limit: expected "<count>/<window>" (e.g. "5/15m"), a window of "s", "m", or "h", or "off"`,
    }
  }

  return rateLimitFromMatch(value, match)
}

import { createHash } from 'node:crypto'

/**
 * The `key` a `rate_limit.exceed` line carries. `docs/alignment.md` §2.1
 * forbids an email address in `data`, and an ip is the same kind of fact
 * about a visitor — so neither is ever the raw value the counter is filed
 * under. A short digest still lets one log reader see the same key trip
 * twice without naming who it belongs to.
 */
export function redactedRateLimitKey(key: string): string {
  return createHash('sha256').update(key).digest('hex').slice(0, 16)
}

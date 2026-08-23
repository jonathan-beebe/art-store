import { parseArgs } from 'node:util'
import { parseAsOfDay } from '../core/escrow/payout-day.ts'

/**
 * Reads `--as-of=2026-08-24` or `--as-of 2026-08-24` off a command line.
 * Without the flag the caller's fallback stands, so a cron entry needs no
 * date and a re-run of a past period can name one. Any flag other than
 * `--as-of` is a caller mistake, so `parseArgs` throws rather than silently
 * ignoring it.
 */
export function parseAsOf(argv: readonly string[], fallback: Date): Date {
  const { values } = parseArgs({
    args: [...argv],
    options: { 'as-of': { type: 'string' } },
    strict: true,
  })

  return parseAsOfDay(values['as-of'], fallback)
}

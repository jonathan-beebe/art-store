const AS_OF_FLAG = '--as-of='

/**
 * Reads `--as-of=2026-08-24` off a command line. Without the flag the caller's
 * fallback stands, so a cron entry needs no date and a re-run of a past period
 * can name one.
 */
export function parseAsOf(argv: readonly string[], fallback: Date): Date {
  const flag = argv.find((argument) => argument.startsWith(AS_OF_FLAG))
  if (flag === undefined) return fallback

  const asOf = new Date(flag.slice(AS_OF_FLAG.length))
  if (Number.isNaN(asOf.getTime())) {
    throw new Error(`${AS_OF_FLAG}DATE needs a date, got ${JSON.stringify(flag)}`)
  }

  return asOf
}

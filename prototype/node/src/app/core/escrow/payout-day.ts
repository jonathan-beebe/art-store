const DAY_PATTERN = /^\d{4}-\d{2}-\d{2}$/

/**
 * Which calendar day a payout run is "as of": the UTC midnight of the day
 * `value` names, or `fallback` when no value was given at all. Shared by the
 * payout CLI (an argv flag) and the admin payouts route (a form field), so
 * "which day is this run for" has one answer.
 */
export function parseAsOfDay(value: string | undefined, fallback: Date): Date {
  if (value === undefined) return fallback

  if (!DAY_PATTERN.test(value)) {
    throw new Error(`as-of day must be YYYY-MM-DD, got ${JSON.stringify(value)}`)
  }

  return new Date(`${value}T00:00:00.000Z`)
}

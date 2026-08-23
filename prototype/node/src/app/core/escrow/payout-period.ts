/**
 * The Monday-to-Sunday week a payout run settles: the most recently
 * completed one as of a given moment.
 */
export type PayoutPeriod = { firstDay: string; lastDay: string }

function toUtcDateString(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function shiftUtcDays(date: Date, days: number): Date {
  const shifted = new Date(date.getTime())
  shifted.setUTCDate(shifted.getUTCDate() + days)
  return shifted
}

export function payoutPeriodEndingBefore(asOf: Date): PayoutPeriod {
  const daysSinceMonday = (((asOf.getUTCDay() - 1) % 7) + 7) % 7
  const firstDay = shiftUtcDays(asOf, -(daysSinceMonday + 7))
  const lastDay = shiftUtcDays(firstDay, 6)
  return { firstDay: toUtcDateString(firstDay), lastDay: toUtcDateString(lastDay) }
}

// Everything dated at or before this instant belongs to the period, which is
// what makes a second run of the same period a no-op.
export function payoutPeriodEndsAt(period: PayoutPeriod): Date {
  return new Date(`${period.lastDay}T23:59:59.999Z`)
}

export function payoutPeriodCovers(period: PayoutPeriod, moment: Date): boolean {
  const day = toUtcDateString(moment)
  return day >= period.firstDay && day <= period.lastDay
}

export function payoutPeriodLabel(period: PayoutPeriod): string {
  return `${period.firstDay} to ${period.lastDay}`
}

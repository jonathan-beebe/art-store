import { activityTotals, type ActivityTotals } from './activity-totals.ts'
import type { ListingEventType } from '../listings/listing-event-type.ts'

export type DailyActivity = { day: string; totals: ActivityTotals }

function shiftUtcDays(date: Date, days: number): Date {
  const shifted = new Date(date.getTime())
  shifted.setUTCDate(shifted.getUTCDate() + days)
  return shifted
}

function toDay(date: Date): string {
  return date.toISOString().slice(0, 10)
}

/**
 * A gapless run of days ending on `endsOn`, oldest first, so a listing's
 * activity table keeps a row for every day a seller looks at even when
 * nothing happened on it.
 */
export function activityTimeline(
  countsByDay: Readonly<Record<string, Partial<Record<ListingEventType, number>>>>,
  input: { endsOn: Date; days: number },
): readonly DailyActivity[] {
  const { endsOn, days } = input
  if (days < 1) {
    throw new RangeError(`a timeline covers at least one day, got ${days}`)
  }

  return Array.from({ length: days }, (_, index) => {
    const day = toDay(shiftUtcDays(endsOn, index - (days - 1)))
    return { day, totals: activityTotals(countsByDay[day] ?? {}) }
  })
}

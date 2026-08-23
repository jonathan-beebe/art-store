import { activityTotals, type ActivityTotals } from './activity-totals.ts'
import type { ListingEventType } from '../listings/listing-event-type.ts'

export type DailyActivity = { day: string; totals: ActivityTotals }

export type ActivityWindow = { since: Date; days: number }

function shiftUtcDays(date: Date, days: number): Date {
  const shifted = new Date(date.getTime())
  shifted.setUTCDate(shifted.getUTCDate() + days)
  return shifted
}

function toDay(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function requireAtLeastOneDay(days: number): void {
  if (days < 1) {
    throw new RangeError(`a window covers at least one day, got ${days}`)
  }
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
  requireAtLeastOneDay(days)

  return Array.from({ length: days }, (_, index) => {
    const day = toDay(shiftUtcDays(endsOn, index - (days - 1)))
    return { day, totals: activityTotals(countsByDay[day] ?? {}) }
  })
}

/**
 * The query boundary a timeline's days need: midnight UTC on the earliest day
 * `activityTimeline` will render, built from the same day-shift so a caller
 * that passes this `since` to its query and `{ endsOn, days }` to the timeline
 * never fetches a day the timeline does not show, or shows a day it did not fetch.
 */
export function activityWindow(endsOn: Date, days: number): ActivityWindow {
  requireAtLeastOneDay(days)

  const firstDay = shiftUtcDays(endsOn, -(days - 1))
  const since = new Date(`${toDay(firstDay)}T00:00:00.000Z`)

  return { since, days }
}

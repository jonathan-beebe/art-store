import { fromTimestamp, type Timestamp } from '../../db/timestamp.ts'

const MONTH_ABBREVIATIONS = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
] as const

/** A report day (`YYYY-MM-DD`) as a table row reads it: `Aug 9`. Reads no
 * `Intl` locale data, so it renders the same in every environment. */
export function formatDay(day: string): string {
  const [, month, date] = day.split('-').map(Number)
  return `${MONTH_ABBREVIATIONS[(month ?? 1) - 1]} ${date}`
}

/** A stored UTC timestamp as a calendar date: `Aug 9, 2026`. */
export function formatDate(timestamp: Timestamp): string {
  const instant = fromTimestamp(timestamp)
  return `${MONTH_ABBREVIATIONS[instant.getUTCMonth()]} ${instant.getUTCDate()}, ${instant.getUTCFullYear()}`
}

/** A stored UTC timestamp with a 12-hour clock time: `Aug 9, 2026 3:04pm`. */
export function formatDateTime(timestamp: Timestamp): string {
  const instant = fromTimestamp(timestamp)
  const hour24 = instant.getUTCHours()
  const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12
  const minute = String(instant.getUTCMinutes()).padStart(2, '0')
  const meridiem = hour24 < 12 ? 'am' : 'pm'

  return `${formatDate(timestamp)} ${hour12}:${minute}${meridiem}`
}

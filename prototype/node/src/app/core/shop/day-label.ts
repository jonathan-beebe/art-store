// Timestamps are stored in UTC, so every label here reads in UTC too — an
// order placed late in the evening keeps the date the receipt already showed.
// Locale and time zone are explicit on every formatter so a label reads the
// same in every environment, whatever locale data the host ships.

const DAY_FORMAT = new Intl.DateTimeFormat('en-GB', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'UTC',
})

const DATE_FORMAT = new Intl.DateTimeFormat('en-US', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  timeZone: 'UTC',
})

const REPORT_DAY_FORMAT = new Intl.DateTimeFormat('en-US', {
  day: 'numeric',
  month: 'short',
  timeZone: 'UTC',
})

const DATE_TIME_FORMAT = new Intl.DateTimeFormat('en-US', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
  hour12: true,
  timeZone: 'UTC',
})

const TIMESTAMP_FORMAT = new Intl.DateTimeFormat('en-US', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
  timeZone: 'UTC',
})

function partsByType(format: Intl.DateTimeFormat, instant: Date): Record<string, string> {
  return Object.fromEntries(format.formatToParts(instant).map((part) => [part.type, part.value]))
}

/** The calendar day an ISO-8601 instant falls on, as a page prints it. */
export function dayLabel(instant: string): string {
  return DAY_FORMAT.format(new Date(instant))
}

/** A stored instant as a calendar date: `Aug 9, 2026`. */
export function dateLabel(instant: string): string {
  return DATE_FORMAT.format(new Date(instant))
}

/**
 * A stored instant with a 12-hour clock time: `Aug 9, 2026 3:04pm`. Composed
 * from parts rather than `format()` directly: `Intl`'s own rendering puts a
 * comma and a space before the meridiem and capitalizes it (`3:04 PM`), which
 * this label keeps compact and lowercase instead.
 */
export function dateTimeLabel(instant: string): string {
  const parts = partsByType(DATE_TIME_FORMAT, new Date(instant))
  const meridiem = (parts.dayPeriod ?? '').toLowerCase()
  return `${parts.month} ${parts.day}, ${parts.year} ${parts.hour}:${parts.minute}${meridiem}`
}

/** A stored instant to the minute, sortable as printed: `2026-08-24 12:00`. */
export function timestampLabel(instant: string): string {
  const parts = partsByType(TIMESTAMP_FORMAT, new Date(instant))
  return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}`
}

/** A report day (`YYYY-MM-DD`) as a table row reads it: `Aug 9`. */
export function dayFromReportKey(day: string): string {
  return REPORT_DAY_FORMAT.format(new Date(day))
}

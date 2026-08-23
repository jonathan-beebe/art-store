// Timestamps are stored in UTC, so the label is read in UTC too: an order
// placed late in the evening keeps the date the receipt already showed.
const DAY_FORMAT = new Intl.DateTimeFormat('en-GB', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'UTC',
})

/** The calendar day an ISO-8601 instant falls on, as a page prints it. */
export function dayLabel(instant: string): string {
  return DAY_FORMAT.format(new Date(instant))
}

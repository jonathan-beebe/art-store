import { timestampLabel } from '../../core/shop/day-label.ts'
import { formatCents } from '../../core/money.ts'
import { statusLabel } from '../../core/status-label.ts'
import type { Timestamp } from '../../db/timestamp.ts'

/** What a column shows when the row has no value there. */
const NOTHING = '—'

/**
 * The pure functions every admin template calls. Templates cannot import, so
 * money, instants, and machine names reach them as page data.
 */
const VIEW_HELPERS = { formatCents, formatMoment, statusLabel }

/**
 * The render data every admin page starts from. Money, instants, and machine
 * names reach the templates as functions rather than as pre-formatted strings,
 * so a table renders a row as it reads it and the queries stay in cents.
 */
export function adminPage<T extends Record<string, unknown>>(
  title: string,
  data: T,
): typeof VIEW_HELPERS & T & { title: string } {
  return { ...VIEW_HELPERS, ...data, title }
}

/** An ISO instant as the minute a person reads: `2026-08-24 12:00`. */
export function formatMoment(value: Timestamp | null): string {
  return value === null ? NOTHING : timestampLabel(value)
}

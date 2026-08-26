import { timestampLabel } from '../../core/shop/day-label.ts'
import { formatCents } from '../../core/money.ts'
import { statusLabel } from '../../core/status-label.ts'
import type { Timestamp } from '../../db/timestamp.ts'

/** What a column shows when the row has no value there. */
const NOTHING = '—'

/**
 * The pure functions every admin template calls. Templates cannot import, so
 * money, instants, machine names, and id links reach them as page data.
 */
const VIEW_HELPERS = { formatCents, formatJson, formatMoment, idHref, linkedIds, statusLabel }

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

/** Stored JSON text, indented for reading; text that is not JSON as it stands. */
export function formatJson(text: string): string {
  try {
    return JSON.stringify(JSON.parse(text), null, 2)
  } catch {
    return text
  }
}

/** The prefixes whose ids have a detail page, per the pages table in
 * `docs/admin.md` — drawn from there so a link never 404s. */
const DETAIL_PAGES: Readonly<Record<string, string>> = {
  ord: '/admin/orders',
  cus: '/admin/customers',
  sel: '/admin/sellers',
  lst: '/admin/listings',
  ful: '/admin/fulfillments',
  obx: '/admin/outbox',
  cnv: '/admin/messages',
}

/** A prefixed id anywhere in the log viewer's text. */
const PREFIXED_ID = /[a-z]{3}_[0-9A-HJKMNP-TV-Z]{26}/g

const HTML_ESCAPES: Readonly<Record<string, string>> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
}

function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (character) => HTML_ESCAPES[character] ?? character)
}

/**
 * Where a prefixed id leads: its detail page where one exists, back into the
 * log list as a filter for the two correlation prefixes, and null — rendered
 * plain — for everything else.
 */
export function idHref(id: string): string | null {
  const prefix = id.slice(0, 3)

  if (prefix === 'txn') return `/admin/logs?txn=${id}`
  if (prefix === 'ses') return `/admin/logs?session=${id}`

  const listPath = DETAIL_PAGES[prefix]

  return listPath === undefined ? null : `${listPath}/${id}`
}

/**
 * Text as safe HTML with every linkable prefixed id wrapped in an anchor, for
 * a template to print with `<%- %>`. Everything that is not a linked id is
 * escaped here, the same way `<%= %>` would have.
 */
export function linkedIds(text: string): string {
  let html = ''
  let consumed = 0

  for (const match of text.matchAll(PREFIXED_ID)) {
    const id = match[0]
    const href = idHref(id)

    html += escapeHtml(text.slice(consumed, match.index))
    html += href === null ? id : `<a href="${href}" class="underline">${id}</a>`
    consumed = match.index + id.length
  }

  return html + escapeHtml(text.slice(consumed))
}

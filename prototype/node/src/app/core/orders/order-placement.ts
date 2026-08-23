import type { ListingStatus } from '../listings/listing-status.ts'

export const UNAVAILABLE_REASONS = ['removed', 'off_sale', 'sold_out', 'short_stock'] as const
export type UnavailableReason = (typeof UNAVAILABLE_REASONS)[number]

/** A cart line as placement judges it: what the cart holds, against what the
 * listing is now. */
export type PlaceableLine = {
  listingId: number
  title: string
  status: ListingStatus
  availableQuantity: number
  quantity: number
  hasActiveRemoval: boolean
}

export type UnavailableLine = {
  listingId: number
  title: string
  reason: UnavailableReason
}

/**
 * Whether a cart may become an order, and what stands in the way when it may
 * not. Generic in the line so the caller keeps whatever else it read alongside
 * the fields placement looks at.
 */
export type OrderPlacement<Line extends PlaceableLine> =
  | { ok: true; lines: readonly Line[] }
  | { ok: false; unavailable: readonly UnavailableLine[] }

function unavailableReason(line: PlaceableLine): UnavailableReason | null {
  if (line.hasActiveRemoval) return 'removed'
  if (line.status === 'sold') return 'sold_out'
  if (line.status !== 'for_sale') return 'off_sale'
  if (line.availableQuantity < 1) return 'sold_out'
  if (line.quantity > line.availableQuantity) return 'short_stock'

  return null
}

/** Every line that stands in the way is named, so one answer covers the cart. */
export function planOrderPlacement<Line extends PlaceableLine>(
  lines: readonly Line[],
): OrderPlacement<Line> {
  const unavailable = lines.flatMap((line) => {
    const reason = unavailableReason(line)

    return reason === null ? [] : [{ listingId: line.listingId, title: line.title, reason }]
  })

  return unavailable.length === 0 ? { ok: true, lines } : { ok: false, unavailable }
}

export type UnavailableNotice = {
  title: string
  notice: string
}

const UNAVAILABLE_NOTICES: Readonly<Record<UnavailableReason, string>> = {
  removed: 'no longer available',
  off_sale: 'no longer for sale',
  sold_out: 'sold out',
  short_stock: 'no longer in stock in that quantity',
}

/** What the checkout page says about each line it cannot buy. */
export function unavailableNotices(
  lines: readonly UnavailableLine[],
): readonly UnavailableNotice[] {
  return lines.map((line) => ({ title: line.title, notice: UNAVAILABLE_NOTICES[line.reason] }))
}

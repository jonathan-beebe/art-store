import type { FastifyReply } from 'fastify'
import { listingImageSource } from '../../core/listings/placeholder-image.ts'
import { formatCents } from '../../core/money.ts'
import { dayLabel } from '../../core/shop/day-label.ts'
import { shopName } from '../../core/shop/shop-name.ts'
import { statusLabel } from '../../core/shop/status-label.ts'

/**
 * The pure functions every storefront template calls. Templates cannot import,
 * so they arrive as page data and money, shop names, and statuses read the one
 * way across the site.
 */
const VIEW_HELPERS = { dayLabel, formatCents, listingImageSource, shopName, statusLabel }

/** The header carries the search box on every page, so every page answers what
 * is in it. */
const PAGE_DEFAULTS = { searchTerm: '' }

export type ShopPageData = Record<string, unknown>

export function shopPage(data: ShopPageData): ShopPageData {
  return { ...VIEW_HELPERS, ...PAGE_DEFAULTS, ...data }
}

/**
 * What a visitor gets for art that was never public, for a listing an admin
 * removed, and for someone else's order: the same page, so nothing here says
 * whether the thing exists.
 */
export function renderNotFound(reply: FastifyReply): FastifyReply {
  return reply.status(404).render('not-found', shopPage({ title: 'Not found' }))
}

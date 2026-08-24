import type { CartId, CartItemId, ListingId, SellerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { cartLineTotal, createCartLine, type CartLine } from '../../core/cart/cart-line.ts'
import { cartTotals, type CartTotals } from '../../core/cart/cart-totals.ts'
import type { ListingStatus } from '../../core/listings/listing-status.ts'
import type { Cents } from '../../core/money.ts'
import { noticeForUnavailableReason, unavailableReason } from '../../core/orders/order-placement.ts'
import { activeListingRemoval } from '../moderation/active-listing-removal.ts'

/** One line of a cart, with the listing details the page and the order need. */
export type CartLineView = {
  cartItemId: CartItemId
  listingId: ListingId
  sellerId: SellerId
  title: string
  slug: string
  imagePath: string | null
  status: ListingStatus
  availableQuantity: number
  unitPriceCents: Cents
  quantity: number
  /** The price a page shows for the line: unit price times quantity. */
  lineTotalCents: Cents
  /** Whether this line could still become part of an order right now. */
  isUnavailable: boolean
  /** What to tell the shopper about an unavailable line; null otherwise. */
  unavailableNotice: string | null
}

export type CartContents = {
  cartId: CartId
  lines: readonly CartLineView[]
  totals: CartTotals
}

/**
 * What is in a cart right now, priced from the listings behind it. A line
 * whose listing an admin removed, or that fell off sale or out of stock since
 * it was added, stays on the cart marked unavailable rather than vanishing —
 * the same judgment checkout makes when the order is placed.
 */
export async function cartContents(
  context: Pick<ActionContext, 'db'>,
  cartId: CartId,
): Promise<CartContents> {
  const { db } = context
  const rows = await db
    .selectFrom('cartItems')
    .innerJoin('listings', 'listings.id', 'cartItems.listingId')
    .select([
      'cartItems.id as cartItemId',
      'cartItems.quantity as quantity',
      'listings.id as listingId',
      'listings.sellerId as sellerId',
      'listings.title as title',
      'listings.slug as slug',
      'listings.imagePath as imagePath',
      'listings.status as status',
      'listings.quantity as availableQuantity',
      'listings.priceCents as unitPriceCents',
    ])
    .where('cartItems.cartId', '=', cartId)
    .orderBy('cartItems.createdAt')
    .orderBy('cartItems.id')
    .execute()

  const lines: CartLineView[] = []
  for (const row of rows) {
    const removal = await activeListingRemoval(context, row.listingId)
    const reason = unavailableReason({
      listingId: row.listingId,
      title: row.title,
      status: row.status,
      availableQuantity: row.availableQuantity,
      quantity: row.quantity,
      hasActiveRemoval: removal !== null,
    })

    lines.push({
      ...row,
      lineTotalCents: cartLineTotal(row),
      isUnavailable: reason !== null,
      unavailableNotice: reason === null ? null : noticeForUnavailableReason(reason),
    })
  }

  const availableLines = lines.filter((line) => !line.isUnavailable)

  return { cartId, lines, totals: cartTotals(availableLines.map(toCartLine)) }
}

/** The priced line behind a view, which is what totals and an order are built from. */
export function toCartLine(line: CartLineView): CartLine {
  return createCartLine({
    sellerId: line.sellerId,
    unitPriceCents: line.unitPriceCents,
    quantity: line.quantity,
  })
}

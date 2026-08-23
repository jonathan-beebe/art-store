import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { cartContents, toCartLine, type CartLineView } from '../carts/cart-contents.ts'
import { activeListingRemoval } from '../moderation/active-listing-removal.ts'
import { checkoutTotals, type CartTotals } from '../../core/cart/cart-totals.ts'
import { platformFee, sellerNet } from '../../core/escrow/fee.ts'
import { stockAfterSale } from '../../core/listings/listing-stock.ts'
import { planOrderPlacement, type UnavailableLine } from '../../core/orders/order-placement.ts'
import { orderStatusForPlacement } from '../../core/orders/order-status.ts'
import type { Purchaser } from '../../core/orders/purchaser.ts'
import type { ShippingAddress } from '../../core/orders/shipping-address.ts'
import type { Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type PlaceOrderInput = {
  cartId: number
  purchaser: Purchaser
  shipping: ShippingAddress
}

/** Either the order, or the cart lines that stopped it being placed. */
export type PlacedOrder =
  | { ok: true; order: Order }
  | { ok: false; unavailable: readonly UnavailableLine[] }

type PlacementLine = CartLineView & { hasActiveRemoval: boolean }

/**
 * Turns a cart into an order: a snapshot of every item, one fulfillment per
 * seller priced once here, and the stock claimed. A fee-rate change must never
 * re-price an order already placed, so `feeCents` and `netCents` are stored on
 * the fulfillment and every later step moves those numbers.
 *
 * A cart sits for as long as its owner leaves it there, so what it holds is
 * read and judged inside this transaction: a line that can no longer be bought
 * comes back as a refusal with nothing written.
 */
export async function placeOrder(
  context: ActionContext,
  input: PlaceOrderInput,
): Promise<PlacedOrder> {
  return runInTransaction(context, async (transacted) => {
    const contents = await cartContents(transacted, input.cartId)
    const placement = planOrderPlacement(await withRemovals(transacted, contents.lines))
    if (!placement.ok) return { ok: false, unavailable: placement.unavailable }

    const totals = checkoutTotals(placement.lines.map(toCartLine))
    const order = await openOrder(transacted, input, totals)
    await snapshotItems(transacted, order.id, placement.lines)
    await splitBySeller(transacted, order.id, totals)
    await takeStock(transacted, placement.lines)
    await transacted.db.deleteFrom('cartItems').where('cartId', '=', contents.cartId).execute()

    return { ok: true, order }
  })
}

/**
 * The placed order, for a caller that built the listings it is buying. Seeds
 * and fixtures know their own cart, so a refusal there is a broken caller.
 */
export async function placeOrderOrThrow(
  context: ActionContext,
  input: PlaceOrderInput,
): Promise<Order> {
  const placement = await placeOrder(context, input)

  if (!placement.ok) {
    const titles = placement.unavailable.map((line) => line.title).join(', ')
    throw new Error(`a cart holding ${titles} cannot become an order`)
  }

  return placement.order
}

/** Each line beside the admin removal that stands over its listing. */
async function withRemovals(
  context: ActionContext,
  lines: readonly CartLineView[],
): Promise<readonly PlacementLine[]> {
  const judged: PlacementLine[] = []

  for (const line of lines) {
    const removal = await activeListingRemoval(context, line.listingId)
    judged.push({ ...line, hasActiveRemoval: removal !== null })
  }

  return judged
}

async function openOrder(
  { db, clock }: ActionContext,
  input: PlaceOrderInput,
  totals: CartTotals,
): Promise<Order> {
  return db
    .insertInto('orders')
    .values({
      customerId: input.purchaser.id,
      email: input.purchaser.email,
      status: orderStatusForPlacement(input.purchaser.isEmailVerified),
      shippingName: input.shipping.name,
      shippingLine1: input.shipping.line1,
      shippingLine2: input.shipping.line2,
      shippingCity: input.shipping.city,
      shippingRegion: input.shipping.region,
      shippingPostalCode: input.shipping.postalCode,
      shippingCountry: input.shipping.country,
      subtotalCents: totals.subtotalCents,
      totalCents: totals.subtotalCents,
      placedAt: toTimestamp(clock.now()),
      finalizedAt: null,
      cancelledAt: null,
    })
    .returningAll()
    .executeTakeFirstOrThrow()
}

async function snapshotItems(
  { db }: ActionContext,
  orderId: number,
  lines: readonly CartLineView[],
): Promise<void> {
  await db
    .insertInto('orderItems')
    .values(
      lines.map((line) => ({
        orderId,
        listingId: line.listingId,
        sellerId: line.sellerId,
        title: line.title,
        unitPriceCents: line.unitPriceCents,
        quantity: line.quantity,
      })),
    )
    .execute()
}

async function splitBySeller(
  { db }: ActionContext,
  orderId: number,
  totals: CartTotals,
): Promise<void> {
  await db
    .insertInto('fulfillments')
    .values(
      totals.subtotalsBySeller.map((seller) => ({
        orderId,
        sellerId: seller.sellerId,
        status: 'awaiting_shipment' as const,
        carrier: null,
        trackingNumber: null,
        subtotalCents: seller.subtotalCents,
        feeCents: platformFee(seller.subtotalCents),
        netCents: sellerNet(seller.subtotalCents),
        shippedAt: null,
        deliveredAt: null,
      })),
    )
    .execute()
}

async function takeStock({ db, clock }: ActionContext, lines: readonly CartLineView[]): Promise<void> {
  const updatedAt = toTimestamp(clock.now())

  for (const line of lines) {
    const stock = stockAfterSale({
      quantity: line.availableQuantity,
      status: line.status,
      sold: line.quantity,
    })

    await db
      .updateTable('listings')
      .set({ quantity: stock.quantity, status: stock.status, updatedAt })
      .where('id', '=', line.listingId)
      .execute()
  }
}

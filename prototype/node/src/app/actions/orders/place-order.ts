import type { CartId, OrderId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { cartContents, toCartLine, type CartLineView } from '../carts/cart-contents.ts'
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
  cartId: CartId
  purchaser: Purchaser
  shipping: ShippingAddress
}

/** Either the order, or the cart lines that stopped it being placed. */
export type PlacedOrder =
  | { ok: true; order: Order }
  | { ok: false; unavailable: readonly UnavailableLine[] }

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
  return actionStory<PlacedOrder>(
    context,
    {
      event: 'order.place',
      will: {
        msg: 'placing an order from the cart',
        data: { cart_id: input.cartId, customer_id: input.purchaser.id },
      },
      ended: (placement) =>
        placement.ok
          ? {
              phase: 'did',
              msg: 'placed the order',
              data: {
                order_id: placement.order.id,
                total_cents: placement.order.totalCents,
                status: placement.order.status,
              },
            }
          : {
              phase: 'refused',
              msg: 'the cart holds lines that can no longer be bought',
              data: {
                cart_id: input.cartId,
                unavailable: placement.unavailable.map((line) => ({
                  listing_id: line.listingId,
                  reason: line.reason,
                })),
              },
            },
    },
    async (transacted) => {
      const contents = await cartContents(transacted, input.cartId)
      const placement = planOrderPlacement(contents.lines)
      if (!placement.ok) return { ok: false, unavailable: placement.unavailable }

      const totals = checkoutTotals(placement.lines.map(toCartLine))
      const order = await openOrder(transacted, input, totals)
      await snapshotItems(transacted, order.id, placement.lines)
      await splitBySeller(transacted, order.id, totals)
      await takeStock(transacted, placement.lines)
      await transacted.db.deleteFrom('cartItems').where('cartId', '=', contents.cartId).execute()

      return { ok: true, order }
    },
  )
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

async function openOrder(
  { db, clock }: ActionContext,
  input: PlaceOrderInput,
  totals: CartTotals,
): Promise<Order> {
  return db
    .insertInto('orders')
    .values({
      id: newId('ord', clock.now()),
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
  { db, clock }: ActionContext,
  orderId: OrderId,
  lines: readonly CartLineView[],
): Promise<void> {
  await db
    .insertInto('orderItems')
    .values(
      lines.map((line) => ({
        id: newId('oit', clock.now()),
        orderId,
        listingId: line.listingId,
        sellerId: line.sellerId,
        title: line.title,
        unitPriceCents: line.unitPriceCents,
        quantity: line.quantity,
        createdAt: toTimestamp(clock.now()),
      })),
    )
    .execute()
}

/** One fulfillment per seller on the order, each born `awaiting_shipment` —
 * the status the column defaults to. */
async function splitBySeller(
  { db, clock }: ActionContext,
  orderId: OrderId,
  totals: CartTotals,
): Promise<void> {
  await db
    .insertInto('fulfillments')
    .values(
      totals.subtotalsBySeller.map((seller) => ({
        id: newId('ful', clock.now()),
        orderId,
        sellerId: seller.sellerId,
        carrier: null,
        trackingNumber: null,
        subtotalCents: seller.subtotalCents,
        feeCents: platformFee(seller.subtotalCents),
        netCents: sellerNet(seller.subtotalCents),
        createdAt: toTimestamp(clock.now()),
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

import type { CartId, CartItemId, ListingId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { runInTransaction } from '../transaction.ts'
import { recordListingEvent } from '../listings/record-listing-event.ts'
import { quantityWithinStock } from '../../core/cart/cart-quantity.ts'
import type { CartItem } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type AddToCartInput = {
  cartId: CartId
  listingId: ListingId
  quantity: number
}

/**
 * Adds to the line the cart already holds for a listing, clamped to what the
 * seller has left, and files the interest against the listing.
 *
 * A cart that already holds the listing is an update rather than an add, and
 * the log says which — so the read that decides it happens before the story
 * opens, inside the same transaction the write runs in.
 */
export async function addToCart(context: ActionContext, input: AddToCartInput): Promise<CartItem> {
  return runInTransaction(context, async (transacted) => {
    const { db } = transacted
    const cart = await db
      .selectFrom('carts')
      .select('customerId')
      .where('id', '=', input.cartId)
      .executeTakeFirstOrThrow()

    const listing = await db
      .selectFrom('listings')
      .select('quantity')
      .where('id', '=', input.listingId)
      .executeTakeFirstOrThrow()

    const held = await db
      .selectFrom('cartItems')
      .selectAll()
      .where('cartId', '=', input.cartId)
      .where('listingId', '=', input.listingId)
      .executeTakeFirst()

    const quantity = quantityWithinStock({
      requested: (held?.quantity ?? 0) + input.quantity,
      available: listing.quantity,
    })

    return actionStory<CartItem>(
      transacted,
      {
        event: held === undefined ? 'cart.add' : 'cart.update',
        will: {
          msg: held === undefined ? 'adding the listing to the cart' : 'changing the cart line',
          data: {
            cart_id: input.cartId,
            listing_id: input.listingId,
            quantity_from: held?.quantity ?? 0,
            quantity_to: quantity,
          },
        },
        ended: (item) => ({
          phase: 'did',
          msg: held === undefined ? 'added the listing to the cart' : 'changed the cart line',
          data: { cart_id: item.cartId, listing_id: item.listingId, quantity: item.quantity },
        }),
      },
      async (writing) => {
        const item = await writeCartItem(writing, input, quantity, held?.id)

        await recordListingEvent(writing, {
          listingId: input.listingId,
          customerId: cart.customerId,
          eventType: 'cart_add',
        })

        return item
      },
    )
  })
}

async function writeCartItem(
  { db, clock }: ActionContext,
  input: AddToCartInput,
  quantity: number,
  heldItemId: CartItemId | undefined,
): Promise<CartItem> {
  if (heldItemId !== undefined) {
    return db
      .updateTable('cartItems')
      .set({ quantity })
      .where('id', '=', heldItemId)
      .returningAll()
      .executeTakeFirstOrThrow()
  }

  return db
    .insertInto('cartItems')
    .values({
      id: newId('cti', clock.now()),
      cartId: input.cartId,
      listingId: input.listingId,
      quantity,
      createdAt: toTimestamp(clock.now()),
    })
    .returningAll()
    .executeTakeFirstOrThrow()
}

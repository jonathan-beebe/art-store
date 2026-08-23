import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { recordListingEvent } from '../listings/record-listing-event.ts'
import { quantityWithinStock } from '../../core/cart/cart-quantity.ts'
import type { CartItem } from '../../db/commerce-schema.ts'

export type AddToCartInput = {
  cartId: number
  listingId: number
  quantity: number
}

/**
 * Adds to the line the cart already holds for a listing, clamped to what the
 * seller has left, and files the interest against the listing.
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

    const item = await writeCartItem(transacted, input, quantity, held?.id)

    await recordListingEvent(transacted, {
      listingId: input.listingId,
      customerId: cart.customerId,
      eventType: 'cart_add',
    })

    return item
  })
}

async function writeCartItem(
  { db }: ActionContext,
  input: AddToCartInput,
  quantity: number,
  heldItemId: number | undefined,
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
    .values({ cartId: input.cartId, listingId: input.listingId, quantity })
    .returningAll()
    .executeTakeFirstOrThrow()
}

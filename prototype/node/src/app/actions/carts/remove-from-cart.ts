import type { CartId, ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'

export type RemoveFromCartInput = {
  cartId: CartId
  listingId: ListingId
}

/** Drops a listing's line from the cart. Removing one the cart never held is a no-op. */
export async function removeFromCart(
  context: ActionContext,
  input: RemoveFromCartInput,
): Promise<void> {
  await actionStory<number>(
    context,
    {
      event: 'cart.remove',
      will: {
        msg: 'taking the listing out of the cart',
        data: { cart_id: input.cartId, listing_id: input.listingId },
      },
      ended: (removed) => ({
        phase: 'did',
        msg: removed === 0 ? 'the cart held no such line' : 'took the listing out of the cart',
        data: { cart_id: input.cartId, listing_id: input.listingId, removed_lines: removed },
      }),
    },
    async ({ db }) => {
      const deleted = await db
        .deleteFrom('cartItems')
        .where('cartId', '=', input.cartId)
        .where('listingId', '=', input.listingId)
        .executeTakeFirst()

      return Number(deleted.numDeletedRows)
    },
  )
}

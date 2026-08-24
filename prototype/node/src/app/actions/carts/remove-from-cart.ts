import type { CartId, ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'

export type RemoveFromCartInput = {
  cartId: CartId
  listingId: ListingId
}

/** Drops a listing's line from the cart. Removing one the cart never held is a no-op. */
export async function removeFromCart(
  { db }: ActionContext,
  input: RemoveFromCartInput,
): Promise<void> {
  await db
    .deleteFrom('cartItems')
    .where('cartId', '=', input.cartId)
    .where('listingId', '=', input.listingId)
    .execute()
}

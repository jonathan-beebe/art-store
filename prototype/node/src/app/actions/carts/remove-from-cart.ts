import type { ActionContext } from '../action-context.ts'

export type RemoveFromCartInput = {
  cartId: number
  listingId: number
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

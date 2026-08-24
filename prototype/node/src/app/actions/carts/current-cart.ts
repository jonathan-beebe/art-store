import type { CustomerId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { Cart } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/**
 * The cart a customer is shopping with, opened on first ask. A merge hands the
 * verified customer whatever cart the anonymous visitor was filling, so one
 * customer can own two; the one holding the most items is theirs.
 */
export async function currentCart(context: ActionContext, customerId: CustomerId): Promise<Cart> {
  return runInTransaction(context, async ({ db, clock }) => {
    const existing = await db
      .selectFrom('carts')
      .leftJoin('cartItems', 'cartItems.cartId', 'carts.id')
      .select(({ fn }) => ['carts.id', 'carts.customerId', 'carts.createdAt', fn.count<string | number | bigint>('cartItems.id').as('itemCount')])
      .where('carts.customerId', '=', customerId)
      .groupBy('carts.id')
      .orderBy('itemCount', 'desc')
      .orderBy('carts.createdAt', 'desc')
      .orderBy('carts.id', 'desc')
      .executeTakeFirst()

    if (existing !== undefined) {
      return { id: existing.id, customerId: existing.customerId, createdAt: existing.createdAt }
    }

    return db
      .insertInto('carts')
      .values({ id: newId('crt', clock.now()), customerId, createdAt: toTimestamp(clock.now()) })
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

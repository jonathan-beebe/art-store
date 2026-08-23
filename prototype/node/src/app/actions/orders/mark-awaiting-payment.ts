import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { orderStatusAfterVerification } from '../../core/orders/order-status.ts'
import type { Order } from '../../db/commerce-schema.ts'

/**
 * Verifying an email is what lets a guest's order reach the card form. The pay
 * page calls this on every hit, so it has to be a no-op once the order has
 * already moved.
 */
export async function markAwaitingPayment(
  context: ActionContext,
  orderId: number,
): Promise<Order> {
  return runInTransaction(context, async ({ db }) => {
    const order = await db
      .selectFrom('orders')
      .selectAll()
      .where('id', '=', orderId)
      .executeTakeFirstOrThrow()

    return db
      .updateTable('orders')
      .set({ status: orderStatusAfterVerification(order.status) })
      .where('id', '=', order.id)
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { orderStatusFromFulfillments } from '../../core/orders/order-status.ts'
import type { Order } from '../../db/commerce-schema.ts'

/** An order that spans sellers reads its status back from its fulfillments. */
export async function rollUpOrderStatus(context: ActionContext, orderId: number): Promise<Order> {
  return runInTransaction(context, async ({ db }) => {
    const fulfillments = await db
      .selectFrom('fulfillments')
      .select('status')
      .where('orderId', '=', orderId)
      .orderBy('id')
      .execute()

    return db
      .updateTable('orders')
      .set({ status: orderStatusFromFulfillments(fulfillments.map((row) => row.status)) })
      .where('id', '=', orderId)
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

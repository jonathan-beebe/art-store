import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { moveOrderStock } from './move-order-stock.ts'
import { notify } from '../notifications/notify.ts'
import { holdMovement } from '../../core/escrow/ledger-movement.ts'
import { itemSoldMessage } from '../../core/notifications/notification-message.ts'
import { paymentAttemptFor, settledFulfillments, type PaymentAttempt } from '../../core/orders/payment-attempt.ts'
import { decideCard } from '../../core/payments/fake-card.ts'
import type { Fulfillment, Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type FinalizeOrderInput = {
  orderId: number
  cardNumber: string
}

/**
 * Charges the card. One `payments` row per attempt, so two declines followed by
 * an approval leave three; an approval holds each seller's stored net in escrow
 * and tells them, and a decline hands the stock back to the storefront.
 */
export async function finalizeOrder(
  context: ActionContext,
  input: FinalizeOrderInput,
): Promise<Order> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const now = clock.now()
    const order = await db
      .selectFrom('orders')
      .selectAll()
      .where('id', '=', input.orderId)
      .executeTakeFirstOrThrow()

    const attempt = paymentAttemptFor({
      status: order.status,
      decision: decideCard(input.cardNumber),
      now,
    })

    await moveOrderStock(transacted, order.id, attempt.stockChange)
    await recordPayment(transacted, order, attempt)

    const charged = await db
      .updateTable('orders')
      .set({
        status: attempt.orderStatus,
        finalizedAt: attempt.finalizedAt === null ? null : toTimestamp(attempt.finalizedAt),
      })
      .where('id', '=', order.id)
      .returningAll()
      .executeTakeFirstOrThrow()

    const fulfillments = await db
      .selectFrom('fulfillments')
      .selectAll()
      .where('orderId', '=', order.id)
      .orderBy('sellerId')
      .execute()

    for (const fulfillment of settledFulfillments(attempt, fulfillments)) {
      await settle(transacted, fulfillment, now)
    }

    return charged
  })
}

async function recordPayment(
  { db, clock }: ActionContext,
  order: Order,
  attempt: PaymentAttempt,
): Promise<void> {
  await db
    .insertInto('payments')
    .values({
      orderId: order.id,
      status: attempt.paymentStatus,
      amountCents: order.totalCents,
      cardLastFour: attempt.cardLastFour,
      declineReason: attempt.declineReason,
      processedAt: toTimestamp(clock.now()),
    })
    .execute()
}

/** Holds the seller's net and tells them the item sold. */
async function settle(
  context: ActionContext,
  fulfillment: Fulfillment,
  now: Date,
): Promise<void> {
  const hold = holdMovement(fulfillment.netCents)

  await context.db
    .insertInto('ledgerEntries')
    .values({
      sellerId: fulfillment.sellerId,
      fulfillmentId: fulfillment.id,
      payoutId: null,
      entryType: hold.entryType,
      amountCents: hold.amountCents,
      occurredAt: toTimestamp(now),
    })
    .execute()

  await notify(context, {
    recipientType: 'seller',
    recipientId: fulfillment.sellerId,
    message: itemSoldMessage(fulfillment.orderId, fulfillment.netCents, `/seller/orders/${fulfillment.id}`),
  })
}

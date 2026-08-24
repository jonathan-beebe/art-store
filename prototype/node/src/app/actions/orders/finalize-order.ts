import type { OrderId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { writeLedgerEntry } from '../escrow/write-ledger-entry.ts'
import { moveOrderStock } from './move-order-stock.ts'
import { notify } from '../notifications/notify.ts'
import { holdMovement } from '../../core/escrow/ledger-movement.ts'
import { itemSoldMessage } from '../../core/notifications/notification-message.ts'
import { paymentAttemptFor, settledFulfillments, type PaymentAttempt } from '../../core/orders/payment-attempt.ts'
import { decideCard } from '../../core/payments/fake-card.ts'
import type { Fulfillment, Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type FinalizeOrderInput = {
  orderId: OrderId
  cardNumber: string
}

/** The charged order beside the attempt that decided it. */
type Charge = { order: Order; attempt: PaymentAttempt }

/**
 * Charges the card. One `payments` row per attempt, so two declines followed by
 * an approval leave three; an approval holds each seller's stored net in escrow
 * and tells them, and a decline hands the stock back to the storefront.
 *
 * A decline is the domain answering, not a fault, so it closes the story as
 * `refused` and the reason rides on that line.
 */
export async function finalizeOrder(
  context: ActionContext,
  input: FinalizeOrderInput,
): Promise<Order> {
  const charge = await actionStory<Charge>(
    context,
    {
      event: 'order.pay',
      will: { msg: 'charging the card', data: { order_id: input.orderId } },
      ended: ({ order, attempt }) =>
        attempt.paymentStatus === 'approved'
          ? {
              phase: 'did',
              msg: 'charged the card',
              data: {
                order_id: order.id,
                amount_cents: order.totalCents,
                status: order.status,
                card_last_four: attempt.cardLastFour,
              },
            }
          : {
              phase: 'refused',
              msg: 'the card was declined',
              data: {
                order_id: order.id,
                amount_cents: order.totalCents,
                status: order.status,
                decline_reason: attempt.declineReason,
              },
            },
    },
    (transacted) => chargeCard(transacted, input),
  )

  return charge.order
}

async function chargeCard(transacted: ActionContext, input: FinalizeOrderInput): Promise<Charge> {
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

  return { order: charged, attempt }
}

async function recordPayment(
  { db, clock }: ActionContext,
  order: Order,
  attempt: PaymentAttempt,
): Promise<void> {
  await db
    .insertInto('payments')
    .values({
      id: newId('pay', clock.now()),
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
  await writeLedgerEntry(
    context,
    {
      sellerId: fulfillment.sellerId,
      fulfillmentId: fulfillment.id,
      payoutId: null,
      movement: holdMovement(fulfillment.netCents),
      occurredAt: toTimestamp(now),
    },
    now,
  )

  await notify(context, {
    recipientType: 'seller',
    recipientId: fulfillment.sellerId,
    message: itemSoldMessage(fulfillment.orderId, fulfillment.netCents, `/seller/orders/${fulfillment.id}`),
  })
}

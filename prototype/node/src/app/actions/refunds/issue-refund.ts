import type { AdminId, FulfillmentId, PaymentId, SellerId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { writeLedgerEntry } from '../escrow/write-ledger-entry.ts'
import { moveOrderStock } from '../orders/move-order-stock.ts'
import { rollUpOrderStatus } from '../orders/roll-up-order-status.ts'
import { notify } from '../notifications/notify.ts'
import { addCents, ZERO_CENTS, type Cents } from '../../core/money.ts'
import {
  fulfillmentDeclinedMessage,
  refundIssuedMessage,
} from '../../core/notifications/notification-message.ts'
import { fulfillmentTransitionRefusalCopy } from '../../core/orders/fulfillment-status.ts'
import { planRefund } from '../../core/orders/refund.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { Fulfillment, Order, Refund } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** Who is reversing the sale, and which of their ids goes on the row. */
export type RefundIssuer = { type: 'seller'; id: SellerId } | { type: 'admin'; id: AdminId }

export type IssueRefundInput = {
  fulfillmentId: FulfillmentId
  reason: string
  issuedBy: RefundIssuer
}

/** What the reversal left behind: the row, the fulfillment, and the order it rolled up to. */
export type IssuedRefund = { refund: Refund; fulfillment: Fulfillment; order: Order }

export type IssueRefundResult =
  | ({ outcome: 'issued' } & IssuedRefund)
  | Refusal<'order_unpaid' | 'illegal_transition'>

/**
 * Reverses one seller's half of a paid order. A seller's decline puts the
 * pieces back on the storefront; the platform's refund does not, because they
 * have already left the studio. Both hand the customer the whole fulfillment
 * subtotal and take the whole net back off the seller's ledger.
 *
 * Every check the decision makes runs against rows read inside the transaction
 * that writes, so a decline racing a shipment cannot both win.
 */
export async function issueRefund(
  context: ActionContext,
  input: IssueRefundInput,
): Promise<IssueRefundResult> {
  return actionStory<IssueRefundResult>(
    context,
    {
      event: 'refund.issue',
      will: {
        msg: 'issuing a refund',
        data: { fulfillment_id: input.fulfillmentId, issued_by_type: input.issuedBy.type },
      },
      ended: (result) =>
        result.outcome === 'issued'
          ? {
              phase: 'did',
              msg: 'issued the refund',
              data: {
                refund_id: result.refund.id,
                fulfillment_id: result.refund.fulfillmentId,
                amount_cents: result.refund.amountCents,
                reason: result.refund.reason,
              },
            }
          : {
              phase: 'refused',
              msg: 'the refund cannot be issued',
              data: { reason: result.reason, ...result.data },
            },
    },
    (transacted) => reverse(transacted, input),
  )
}

async function reverse(transacted: ActionContext, input: IssueRefundInput): Promise<IssueRefundResult> {
  const { db, clock } = transacted
  const now = clock.now()
  const fulfillment = await db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', input.fulfillmentId)
    .executeTakeFirstOrThrow()

  const plan = planRefund({
    issuedByType: input.issuedBy.type,
    status: fulfillment.status,
    subtotalCents: fulfillment.subtotalCents,
    netCents: fulfillment.netCents,
    paymentId: await approvedPaymentId(transacted, fulfillment),
  })
  if (plan.outcome === 'refused') {
    return refused(plan.reason, {
      fulfillment_id: fulfillment.id,
      order_id: fulfillment.orderId,
      ...plan.data,
    })
  }

  const { intent } = plan
  const reversed = await db
    .updateTable('fulfillments')
    .set({ status: intent.status })
    .where('id', '=', fulfillment.id)
    .returningAll()
    .executeTakeFirstOrThrow()

  const refund = await db
    .insertInto('refunds')
    .values({
      id: newId('rfd', now),
      orderId: fulfillment.orderId,
      fulfillmentId: fulfillment.id,
      paymentId: intent.paymentId,
      amountCents: intent.amountCents,
      reason: input.reason,
      issuedByType: input.issuedBy.type,
      issuedById: input.issuedBy.id,
      createdAt: toTimestamp(now),
    })
    .returningAll()
    .executeTakeFirstOrThrow()

  await writeLedgerEntry(
    transacted,
    {
      sellerId: fulfillment.sellerId,
      fulfillmentId: fulfillment.id,
      payoutId: null,
      movement: intent.movement,
      occurredAt: toTimestamp(now),
    },
    now,
  )

  await moveOrderStock(transacted, fulfillment.orderId, intent.stockChange, {
    sellerId: fulfillment.sellerId,
  })

  const order = await rollUpOrderStatus(transacted, fulfillment.orderId)
  const settled = await recordRefundedTotal(transacted, order)

  await tellTheCounterparts(transacted, { refund, fulfillment: reversed, order: settled }, input.issuedBy)

  return { outcome: 'issued', refund, fulfillment: reversed, order: settled }
}

/** The charge the money came in on: without one there is nothing to refund. */
async function approvedPaymentId(
  { db }: ActionContext,
  fulfillment: Fulfillment,
): Promise<PaymentId | null> {
  const payment = await db
    .selectFrom('payments')
    .select('id')
    .where('orderId', '=', fulfillment.orderId)
    .where('status', '=', 'approved')
    .orderBy('processedAt', 'desc')
    .orderBy('id', 'desc')
    .executeTakeFirst()

  return payment?.id ?? null
}

/** `orders.refunded_cents` is the order's refunds summed, re-read after each one. */
async function recordRefundedTotal({ db }: ActionContext, order: Order): Promise<Order> {
  const refunds = await db
    .selectFrom('refunds')
    .select('amountCents')
    .where('orderId', '=', order.id)
    .execute()

  const refundedCents = refunds.reduce<Cents>((total, row) => addCents(total, row.amountCents), ZERO_CENTS)

  return db
    .updateTable('orders')
    .set({ refundedCents })
    .where('id', '=', order.id)
    .returningAll()
    .executeTakeFirstOrThrow()
}

/**
 * A seller's decline is news for the customer; the platform's refund is news
 * for both sides, since neither of them asked for it.
 */
async function tellTheCounterparts(
  context: ActionContext,
  issued: IssuedRefund,
  issuedBy: RefundIssuer,
): Promise<void> {
  const { refund, fulfillment, order } = issued
  const customerPath = `/orders/${order.id}`
  const sellerPath = `/seller/orders/${fulfillment.id}`

  if (issuedBy.type === 'seller') {
    await notify(context, {
      recipientType: 'customer',
      recipientId: order.customerId,
      message: fulfillmentDeclinedMessage(order.id, refund.amountCents, refund.reason, customerPath),
    })

    return
  }

  await notify(context, {
    recipientType: 'customer',
    recipientId: order.customerId,
    message: refundIssuedMessage(order.id, refund.amountCents, refund.reason, customerPath),
  })

  await notify(context, {
    recipientType: 'seller',
    recipientId: fulfillment.sellerId,
    message: refundIssuedMessage(order.id, refund.amountCents, refund.reason, sellerPath),
  })
}

/** The sentence a refused refund shows, the same on every site that takes
 * one. */
export function refundRefusalCopy(refusal: Refusal<'order_unpaid' | 'illegal_transition'>): string {
  if (refusal.reason === 'order_unpaid') {
    return 'An order that has not been paid cannot be refunded.'
  }

  return fulfillmentTransitionRefusalCopy(refusal)
}

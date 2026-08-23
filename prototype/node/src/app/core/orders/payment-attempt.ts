import { transitionOrder, orderStatusFromCardDecision, type OrderStatus } from './order-status.ts'
import { stockChangeBetween } from './order-stock.ts'
import type { CardDecision } from '../payments/card-decision.ts'
import type { DeclineReason } from '../payments/decline-reason.ts'
import { paymentStatusFromCardDecision, type PaymentStatus } from '../payments/payment-status.ts'
import type { StockChange } from '../listings/stock-change.ts'

/** What one charge attempt does to an order: the status it lands on, the
 * payments row it writes, and what happens to the stock it claimed. */
export type PaymentAttempt = {
  orderStatus: OrderStatus
  paymentStatus: PaymentStatus
  cardLastFour: string
  declineReason: DeclineReason | null
  stockChange: StockChange
  finalizedAt: Date | null
}

export function paymentAttemptFor(input: { status: OrderStatus; decision: CardDecision; now: Date }): PaymentAttempt {
  const { status, decision, now } = input
  const orderStatus = transitionOrder(status, orderStatusFromCardDecision(decision))

  return {
    orderStatus,
    paymentStatus: paymentStatusFromCardDecision(decision),
    cardLastFour: decision.lastFour,
    declineReason: decision.declineReason,
    stockChange: stockChangeBetween({ from: status, to: orderStatus }),
    finalizedAt: orderStatus === 'paid' ? now : null,
  }
}

/** A declined charge settles nothing, so the caller writes no ledger entries
 * and sends no notifications. */
export function settledFulfillments<Row>(attempt: PaymentAttempt, fulfillments: readonly Row[]): readonly Row[] {
  return isPaidAttempt(attempt) ? fulfillments : []
}

export function isPaidAttempt(attempt: PaymentAttempt): boolean {
  return attempt.orderStatus === 'paid'
}

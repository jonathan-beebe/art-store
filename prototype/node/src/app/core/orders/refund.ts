import { refundMovement, type LedgerMovement } from '../escrow/ledger-movement.ts'
import type { PaymentId } from '../ids/entity-ids.ts'
import type { Cents } from '../money.ts'
import type { StockChange } from '../listings/stock-change.ts'
import { canTransitionFulfillment, type FulfillmentStatus } from './fulfillment-status.ts'

/** Who reversed the sale, which is what decides how it reverses. */
export const REFUND_ISSUER_TYPES = ['seller', 'admin'] as const

export type RefundIssuerType = (typeof REFUND_ISSUER_TYPES)[number]

/** The longest reason a decline or a refund carries, per `docs/alignment.md` §4.1. */
export const REFUND_REASON_MAX_LENGTH = 500

/**
 * A seller declines what they will not ship and the piece goes back on the
 * storefront; an admin refunds a sale that already left the studio, so nothing
 * comes back. Either way the customer is owed the whole fulfillment subtotal
 * and the seller's ledger gives up the whole net.
 */
const REVERSALS = {
  seller: { status: 'declined', stockChange: 'restore' },
  admin: { status: 'refunded', stockChange: 'keep' },
} as const satisfies Record<RefundIssuerType, { status: FulfillmentStatus; stockChange: StockChange }>

/** What one reversal comes to, once the rules have allowed it. */
export type RefundIntent = {
  status: FulfillmentStatus
  amountCents: Cents
  movement: LedgerMovement
  stockChange: StockChange
  /** The charge the money goes back against, carried through so the row cannot be written without one. */
  paymentId: PaymentId
}

/** Everything the decision reads: the fulfillment's own figures and the charge behind them. */
export type RefundSubject = {
  issuedByType: RefundIssuerType
  status: FulfillmentStatus
  subtotalCents: Cents
  netCents: Cents
  /** The approved charge the money came in on, or null when the order never paid. */
  paymentId: PaymentId | null
}

export type RefundPlan = { ok: true; intent: RefundIntent } | { ok: false; refusal: string }

/**
 * Whether this reversal happens and what it does. A fulfillment already
 * declined or refunded has no legal move left, which is what refuses the
 * second one; an order that never paid has nothing to refund.
 */
export function planRefund(subject: RefundSubject): RefundPlan {
  const { issuedByType, status, subtotalCents, netCents, paymentId } = subject
  const reversal = REVERSALS[issuedByType]

  if (paymentId === null) {
    return { ok: false, refusal: 'An order that has not been paid cannot be refunded.' }
  }
  if (!canTransitionFulfillment(status, reversal.status)) {
    return { ok: false, refusal: `A fulfillment cannot move from ${status} to ${reversal.status}.` }
  }

  return {
    ok: true,
    intent: {
      status: reversal.status,
      amountCents: subtotalCents,
      movement: refundMovement(netCents),
      stockChange: reversal.stockChange,
      paymentId,
    },
  }
}

export type RefundReasonResult = { ok: true; value: string } | { ok: false; error: string }

/** What a decline or refund form's reason field has to be for the write to run. */
export function parseRefundReason(input: string | null | undefined): RefundReasonResult {
  const reason = (input ?? '').trim()

  if (reason === '') {
    return { ok: false, error: 'Enter a reason.' }
  }
  if (reason.length > REFUND_REASON_MAX_LENGTH) {
    return { ok: false, error: `Keep the reason under ${REFUND_REASON_MAX_LENGTH} characters.` }
  }

  return { ok: true, value: reason }
}

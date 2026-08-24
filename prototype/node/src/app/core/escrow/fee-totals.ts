import { addCents, ZERO_CENTS, type Cents } from '../money.ts'
import { isReversed, type FulfillmentStatus } from '../orders/fulfillment-status.ts'

/**
 * What the platform kept and what it gave back. The fee on a declined or
 * refunded fulfillment is forgone rather than netted off the earned figure, so
 * the two totals read side by side.
 */
export type FeeTotals = { earnedCents: Cents; refundedCents: Cents }

/** A fulfillment as the fee fold reads it: its fee and how it ended. */
export type FeeSubject = { feeCents: Cents; status: FulfillmentStatus }

export const ZERO_FEE_TOTALS: FeeTotals = { earnedCents: ZERO_CENTS, refundedCents: ZERO_CENTS }

export function feeTotals(subjects: readonly FeeSubject[]): FeeTotals {
  let earnedCents = ZERO_CENTS
  let refundedCents = ZERO_CENTS

  for (const subject of subjects) {
    if (isReversed(subject.status)) {
      refundedCents = addCents(refundedCents, subject.feeCents)
    } else {
      earnedCents = addCents(earnedCents, subject.feeCents)
    }
  }

  return { earnedCents, refundedCents }
}

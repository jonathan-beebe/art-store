import { isCardApproved, type CardDecision } from './card-decision.ts'

export const PAYMENT_STATUSES = ['approved', 'declined'] as const
export type PaymentStatus = (typeof PAYMENT_STATUSES)[number]

export function paymentStatusFromCardDecision(decision: CardDecision): PaymentStatus {
  return isCardApproved(decision) ? 'approved' : 'declined'
}

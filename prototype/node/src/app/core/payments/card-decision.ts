import type { DeclineReason } from './decline-reason.ts'

/** What the card processor said about one number. */
export type CardDecision = {
  lastFour: string
  declineReason: DeclineReason | null
}

export function approvedCard(lastFour: string): CardDecision {
  return { lastFour, declineReason: null }
}

export function declinedCard(lastFour: string, reason: DeclineReason): CardDecision {
  return { lastFour, declineReason: reason }
}

export function isCardApproved(decision: CardDecision): boolean {
  return decision.declineReason === null
}

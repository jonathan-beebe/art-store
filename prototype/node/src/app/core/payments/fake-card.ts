import { approvedCard, declinedCard, type CardDecision } from './card-decision.ts'
import type { DeclineReason } from './decline-reason.ts'

const APPROVED_NUMBER = '4242424242424242'

const DECLINED_NUMBERS: Record<string, DeclineReason> = {
  '4000000000000002': 'generic_decline',
  '4000000000009995': 'insufficient_funds',
}

/** The prototype's stand-in for a card processor: the number decides the
 * answer, and nothing but its last four digits is ever kept. */
export function decideCard(cardNumber: string): CardDecision {
  const digits = cardNumber.replace(/\D/g, '')
  const lastFour = digits.length > 4 ? digits.slice(-4) : digits

  if (digits === APPROVED_NUMBER) {
    return approvedCard(lastFour)
  }

  return declinedCard(lastFour, DECLINED_NUMBERS[digits] ?? 'invalid_card_number')
}

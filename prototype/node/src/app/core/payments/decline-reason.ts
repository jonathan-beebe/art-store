export const DECLINE_REASONS = ['generic_decline', 'insufficient_funds', 'invalid_card_number'] as const
export type DeclineReason = (typeof DECLINE_REASONS)[number]

export const DECLINE_MESSAGES: Record<DeclineReason, string> = {
  generic_decline: 'Your card was declined.',
  insufficient_funds: 'Your card has insufficient funds.',
  invalid_card_number: 'That card number is not valid.',
}

export function declineMessage(reason: DeclineReason): string {
  const message = DECLINE_MESSAGES[reason]
  if (message === undefined) {
    throw new Error(`declineMessage: unknown decline reason ${String(reason)}`)
  }
  return message
}

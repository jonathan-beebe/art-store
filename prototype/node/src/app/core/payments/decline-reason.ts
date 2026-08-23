export const DECLINE_REASONS = ['generic_decline', 'insufficient_funds', 'invalid_card_number'] as const
export type DeclineReason = (typeof DECLINE_REASONS)[number]

export const DECLINE_MESSAGES = {
  generic_decline: 'Your card was declined.',
  insufficient_funds: 'Your card has insufficient funds.',
  invalid_card_number: 'That card number is not valid.',
} as const satisfies Record<DeclineReason, string>

// The table covers every reason, so there is nothing to guard against here:
// a new reason without a message is a compile error at the table.
export function declineMessage(reason: DeclineReason): string {
  return DECLINE_MESSAGES[reason]
}

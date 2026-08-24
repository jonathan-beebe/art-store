import type { AdminId, CustomerId, SellerId } from '../ids/entity-ids.ts'
import type { ConversationParticipant } from '../messaging/conversation-access.ts'

export const RECIPIENT_TYPES = ['seller', 'customer', 'admin'] as const

export type RecipientType = (typeof RECIPIENT_TYPES)[number]

/**
 * Whose inbox a notification lands in. The type and the id travel as one pair,
 * so a seller's id cannot be filed under a customer.
 */
export type NotificationRecipient =
  | { recipientType: 'seller'; recipientId: SellerId }
  | { recipientType: 'customer'; recipientId: CustomerId }
  | { recipientType: 'admin'; recipientId: AdminId }

/** The inbox one conversation participant reads. */
export function notificationRecipient(
  participant: ConversationParticipant,
): NotificationRecipient {
  switch (participant.type) {
    case 'seller':
      return { recipientType: 'seller', recipientId: participant.id }
    case 'customer':
      return { recipientType: 'customer', recipientId: participant.id }
    case 'admin':
      return { recipientType: 'admin', recipientId: participant.id }
  }
}

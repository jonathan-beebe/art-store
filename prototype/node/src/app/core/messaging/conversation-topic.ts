import type { ConversationKind } from './conversation-kind.ts'
import type { OrderId } from '../ids/entity-ids.ts'

/** What the subject row of a conversation is called, for the kinds that have one. */
export type ConversationSubjectNames = {
  listingTitle?: string | null
  orderId?: OrderId | null
}

const SUPPORT_TOPIC = 'Art Store support'

const TOPICS: Readonly<Record<ConversationKind, (names: ConversationSubjectNames) => string>> = {
  admin_seller: () => SUPPORT_TOPIC,
  admin_customer: () => SUPPORT_TOPIC,
  fulfillment: ({ orderId }) => (orderId === null || orderId === undefined ? 'an order' : `order ${orderId}`),
  listing_question: ({ listingTitle }) =>
    listingTitle === null || listingTitle === undefined ? 'a listing' : `“${listingTitle}”`,
}

/**
 * What a conversation is about, in the words an inbox row and a notification
 * both use. The two admin kinds are about nothing in particular, so they answer
 * with the desk rather than a subject row.
 */
export function conversationTopic(kind: ConversationKind, names: ConversationSubjectNames = {}): string {
  return TOPICS[kind](names)
}

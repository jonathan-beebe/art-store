import type { ConversationKind } from './conversation-kind.ts'

/** The participant and subject columns of a conversation, as any row carries them. */
export type ConversationSubject = {
  kind: ConversationKind
  sellerId: number | null
  customerId: number | null
  adminId: number | null
  listingId: number | null
  fulfillmentId: number | null
}

/**
 * What a caller supplies to open or find a conversation. Each kind names
 * exactly the participants and the subject it needs — `KIND_SHAPES`
 * (`conversation-kind.ts`) is the same shape enforced for the rows already in
 * the table.
 */
export type ConversationOpening =
  | { kind: 'admin_seller'; adminId: number; sellerId: number }
  | { kind: 'admin_customer'; adminId: number; customerId: number }
  | { kind: 'fulfillment'; sellerId: number; customerId: number; fulfillmentId: number }
  | { kind: 'listing_question'; sellerId: number; customerId: number; listingId: number }

/** Flattens an opening into the row shape, filling the columns this kind leaves out with null. */
export function conversationSubject(opening: ConversationOpening): ConversationSubject {
  switch (opening.kind) {
    case 'admin_seller':
      return {
        kind: opening.kind,
        sellerId: opening.sellerId,
        customerId: null,
        adminId: opening.adminId,
        listingId: null,
        fulfillmentId: null,
      }
    case 'admin_customer':
      return {
        kind: opening.kind,
        sellerId: null,
        customerId: opening.customerId,
        adminId: opening.adminId,
        listingId: null,
        fulfillmentId: null,
      }
    case 'fulfillment':
      return {
        kind: opening.kind,
        sellerId: opening.sellerId,
        customerId: opening.customerId,
        adminId: null,
        listingId: null,
        fulfillmentId: opening.fulfillmentId,
      }
    case 'listing_question':
      return {
        kind: opening.kind,
        sellerId: opening.sellerId,
        customerId: opening.customerId,
        adminId: null,
        listingId: opening.listingId,
        fulfillmentId: null,
      }
  }
}

/** Whether two subjects name the same thread: same kind, same participants, same subject row. */
export function isSameConversationSubject(a: ConversationSubject, b: ConversationSubject): boolean {
  return (
    a.kind === b.kind &&
    a.sellerId === b.sellerId &&
    a.customerId === b.customerId &&
    a.adminId === b.adminId &&
    a.listingId === b.listingId &&
    a.fulfillmentId === b.fulfillmentId
  )
}
